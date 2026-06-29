import { useState, useEffect } from 'react';
import {
  TextInput,
  Checkbox,
  NumberInput,
  Table,
  Pagination,
  Button,
  Flex,
  Text,
  Loader,
  Center,
  Badge,
  ScrollArea,
  Divider,
} from '@mantine/core';
import { modals } from '@mantine/modals';
import axios from 'axios';
import useProjectsStore from '@/hooks/store/useProjectsStore';

export default function InsumosSelectorModal({ selectedProjects, setLoading, formData }) {
  const { moveSelectedProjects } = useProjectsStore();

  const [productos, setProductos] = useState([]);
  const [busqueda, setBusqueda] = useState('');
  const [pagina, setPagina] = useState(1);
  const [total, setTotal] = useState(0);
  const [cargando, setCargando] = useState(false);
  const [seleccionados, setSeleccionados] = useState({});
  const POR_PAGINA = 20;

  useEffect(() => {
    setCargando(true);
    axios
      .get(route('insumos.index'), {
        params: { search: busqueda, page: pagina, per_page: POR_PAGINA },
      })
      .then(({ data }) => {
        setProductos(data.data);
        setTotal(data.total);
      })
      .catch(() => alert('No se pudieron cargar los insumos'))
      .finally(() => setCargando(false));
  }, [busqueda, pagina]);

  const toggleCheck = producto => {
    setSeleccionados(prev => {
      const existe = prev[producto.id];
      if (existe) {
        // si ya estaba, lo quita
        const { [producto.id]: _, ...resto } = prev;
        return resto;
      }
      // si no estaba, lo agrega
      return {
        ...prev,
        [producto.id]: {
          cantidad: 1,
          nombre: producto.nombre,
          almacen: producto.almacen,
          unidad: producto.unidad,
        },
      };
    });
  };

  const setCantidad = (id, valor) => {
    setSeleccionados(prev => ({
      ...prev,
      [id]: { ...prev[id], cantidad: valor },
    }));
  };

  const quitarSeleccionado = id => {
    setSeleccionados(prev => {
      const { [id]: _, ...resto } = prev;
      return resto;
    });
  };

  const insumosElegidos = Object.entries(seleccionados);

  const handleAceptar = () => {
    modals.closeAll();
    moveSelectedProjects(
      selectedProjects,
      setLoading,
      formData,
      insumosElegidos.map(([id, v]) => ({
        // <- insumos seleccionados
        cod_producto: id,
        name: v.nombre,
        almacen: v.almacen,
        unidad: v.unidad ?? '',
        cantidad: v.cantidad,
      }))
    );
  };

  const filas = productos.map(p => (
    <Table.Tr key={p.id}>
      <Table.Td>
        <Checkbox
          checked={!!seleccionados[p.id]}
          onChange={() => toggleCheck(p)}
        />
      </Table.Td>
      <Table.Td>{p.nombre}</Table.Td>
      <Table.Td>{p.almacen}</Table.Td>
      <Table.Td>{p.unidad}</Table.Td>
      <Table.Td>
        <NumberInput
          min={1}
          value={seleccionados[p.id]?.cantidad ?? 1}
          onChange={val => setCantidad(p.id, val)}
          disabled={!seleccionados[p.id]}
          w={80}
        />
      </Table.Td>
    </Table.Tr>
  ));

  return (
    <Flex
      direction='column'
      style={{ height: '70vh' }} /* ocupa todo el alto del modal */
    >
      {/* ── ZONA SUPERIOR: buscador + tabla ── */}
      <Flex
        direction='column'
        gap='md'
        style={{ flex: 1, maxHeight: '420px' }}
      >
        <TextInput
          placeholder='Buscar producto por nombre...'
          value={busqueda}
          onChange={e => {
            setBusqueda(e.target.value);
            setPagina(1);
          }}
        />

        <ScrollArea style={{ flex: 1 }}>
          {cargando ? (
            <Center h={200}>
              <Loader />
            </Center>
          ) : (
            <Table
              striped
              highlightOnHover
              withTableBorder
            >
              <Table.Thead>
                <Table.Tr>
                  <Table.Th />
                  <Table.Th>Nombre</Table.Th>
                  <Table.Th>Almacén</Table.Th>
                   <Table.Th>Unidad</Table.Th>
                  <Table.Th>Cantidad</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {filas.length > 0 ? (
                  filas
                ) : (
                  <Table.Tr>
                    <Table.Td colSpan={4}>
                      <Text
                        c='dimmed'
                        ta='center'
                      >
                        Sin resultados
                      </Text>
                    </Table.Td>
                  </Table.Tr>
                )}
              </Table.Tbody>
            </Table>
          )}
        </ScrollArea>

        <Pagination
          value={pagina}
          onChange={setPagina}
          total={Math.ceil(total / POR_PAGINA)}
        />
      </Flex>

      <Divider my='md' />

      {/* ── ZONA INFERIOR: panel de seleccionados ── */}
      <Flex
        direction='column'
        gap='xs'
        style={{ flex: 1, maxHeight: '320px' }}
      >
        <Flex
          align='center'
          gap='xs'
        >
          <Text
            fw={500}
            size='sm'
          >
            Insumos seleccionados
          </Text>
          <Badge color={insumosElegidos.length > 0 ? 'blue' : 'gray'}>
            {insumosElegidos.length}
          </Badge>
        </Flex>

        {insumosElegidos.length === 0 ? (
          <Text
            c='dimmed'
            size='sm'
          >
            Ninguno seleccionado aún.
          </Text>
        ) : (
          <ScrollArea style={{ maxHeight: '240px' }}>
            <Table
              withTableBorder
              withColumnBorders
            >
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Nombre</Table.Th>
                  <Table.Th>Almacén</Table.Th>
                  <Table.Th>Unidad</Table.Th>
                  <Table.Th>Cantidad</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {insumosElegidos.map(([id, v]) => (
                  <Table.Tr key={id}>
                    <Table.Td>{v.nombre}</Table.Td>
                    <Table.Td>{v.almacen}</Table.Td>
                    <Table.Td>{v.unidad}</Table.Td>
                    <Table.Td>
                      <NumberInput
                        min={1}
                        value={v.cantidad}
                        onChange={val => setCantidad(id, val)}
                        w={80}
                      />
                    </Table.Td>
                    <Table.Td>
                      <Button
                        size='xs'
                        variant='subtle'
                        color='red'
                        onClick={() => quitarSeleccionado(id)}
                      >
                        Quitar
                      </Button>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </ScrollArea>
        )}
      </Flex>

      {/* ── BOTONES ── */}
      <Flex
        justify='flex-end'
        gap='sm'
        mt='md'
      >
        <Button
          variant='default'
          onClick={() => modals.closeAll()}
        >
          Cancelar
        </Button>
        <Button onClick={handleAceptar}>Aceptar</Button>
      </Flex>
    </Flex>
  );
}
