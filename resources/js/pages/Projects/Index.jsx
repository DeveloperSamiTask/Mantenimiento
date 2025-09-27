import { DatePickerInput } from '@mantine/dates';
import { useState } from 'react';
import { currentUrlParams, reloadWithQuery } from '@/utils/url'; // ajusta la ruta según tu estructura
import ArchivedFilterButton from '@/components/ArchivedFilterButton';
import EmptyWithIcon from '@/components/EmptyWithIcon';
import SearchInput from '@/components/SearchInput';
import useAuthorization from '@/hooks/useAuthorization';
import Layout from '@/layouts/MainLayout';
import { redirectTo } from '@/utils/route';
import { usePage } from '@inertiajs/react';
import { Button, Center, Flex, Grid, Group } from '@mantine/core';
import { IconPlus, IconSearch } from '@tabler/icons-react';
import ProjectCard from './Index/ProjectCard';
import { Pagination } from '@mantine/core'; // ✅ Import correcto

/*Por defecto solo debe mostrarme 20 antes de hacer la busqueda
  en el seacrh dos letras o mas en el search de abajo (orden de trabjao)
  consola -> red , puedo ver que me trae
  */

/*Esta es la vista para plan de tareas */
const ProjectsIndex = () => {
  // ✅ CAMBIO 1: items ahora es objeto de paginación
  const { items } = usePage().props;
  const { isAdmin } = useAuthorization();
  const params = currentUrlParams();

  const [dateRange, setDateRange] = useState(
    params.dateRange && params.dateRange[0] && params.dateRange[1]
      ? [new Date(params.dateRange[0]), new Date(params.dateRange[1])]
      : [null, null]
  );

  const search = search => reloadWithQuery({ search, page: 1 }); // ✅ Reset page on search

  const onDateChange = range => {
    if (window.dateRangeTimeout) {
      clearTimeout(window.dateRangeTimeout);
    }

    window.dateRangeTimeout = setTimeout(() => {
      if (range && range.length >= 2 && range[0] && range[1]) {
        const startDate = range[0].toISOString().split('T')[0];
        const endDate = range[1].toISOString().split('T')[0];
        reloadWithQuery({ dateRange: [startDate, endDate], page: 1 }); // ✅ Reset a página 1
      } else {
        reloadWithQuery({ dateRange: null, page: 1 }); // ✅ Reset a página 1
      }
    }, 300);

    setDateRange(range);
  };

  // ✅ CAMBIO 2: Función para cambiar página
  const handlePageChange = (page) => {
    reloadWithQuery({ page });
  };

  return (
    <>
      <Grid justify='space-between' align='center'>
        <Grid.Col span='content'>
          <Group>
            <SearchInput
              placeholder='Buscar ordenes de trabajo'
              search={search}
            />
            <DatePickerInput
              type='range'
              placeholder='Filtrar por fecha'
              value={dateRange}
              onChange={onDateChange}
              clearable
              allowSingleDateInRange
              miw={200}
              valueFormat='DD/MM/YYYY'
            />
            {isAdmin() && <ArchivedFilterButton />}
          </Group>
        </Grid.Col>
        <Grid.Col span='content'>
          {can('crear proyecto') && (
            <Button
              leftSection={<IconPlus size={14} />}
              radius='xl'
              onClick={() => redirectTo('projects.create')}
            >
              Crear
            </Button>
          )}
        </Grid.Col>
      </Grid>

      {/* ✅ CAMBIO 3: Verificar items.data en lugar de items */}
      {items.data && items.data.length ? (
        <>
          <Flex mt='xl' gap='lg' justify='flex-start' align='flex-start' direction='row' wrap='wrap'>
            {/* ✅ CAMBIO 4: Usar items.data.map */}
            {items.data.map(item => (
              <ProjectCard
                item={item}
                key={item.id}
              />
            ))}
          </Flex>

          {/* ✅ CAMBIO 5: Agregar paginación (OPCIONAL pero recomendado) */}
          <Center mt="xl">
            <Pagination
              total={items.meta.last_page}
              value={items.meta.current_page}
              onChange={handlePageChange}
            />
          </Center>
        </>
      ) : (
        <Center mih={400}>
          <EmptyWithIcon
            title='No se encontraron ordenes de trabajo'
            subtitle='O no tienes acceso a ninguno de ellos.'
            icon={IconSearch}
          />
        </Center>
      )}
    </>
  );
};

ProjectsIndex.layout = page => <Layout title='Ordenes de trabajo'>{page}</Layout>;

export default ProjectsIndex;
