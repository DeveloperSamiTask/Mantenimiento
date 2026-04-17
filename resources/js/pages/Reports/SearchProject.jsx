import EmptyWithIcon from '@/components/EmptyWithIcon';
import useForm from '@/hooks/useForm';
import ContainerBox from '@/layouts/ContainerBox';
import Layout from '@/layouts/MainLayout';
import { currentUrlParams } from '@/utils/route';
import { usePage } from '@inertiajs/react';
import { Inertia } from '@inertiajs/inertia';
import { Box, Breadcrumbs, Button, Center, Flex, Group, MultiSelect, Title } from '@mantine/core';
import { DatePickerInput, DatesProvider } from '@mantine/dates';
import { IconClock } from '@tabler/icons-react';
import dayjs from 'dayjs';
import { useState } from 'react';
import { notifications } from '@mantine/notifications';
import { IconDownload } from '@tabler/icons-react';
import { Loader } from '@mantine/core';
import ProjectCard from './ProjectCard';
import { TextInput } from '@mantine/core'; // BIEN: Esto es lo que necesitas en web
/* Esta cosa manda mi informacion al backend */
const SearchProject = () => {
  let { items, games, periods } = usePage().props;

  const [downloadingZip, setDownloadingZip] = useState(false);
  const [downloadingAllFiltered, setDownloadingAllFiltered] = useState(false); // 👈 FALTABA ESTE

  const params = currentUrlParams();

  const [form, submit, updateValue] = useForm('get', route('reports.search-projects'), {
    id: params.id || '',
    groups: params.groups?.map(String) || [],
    games: params.games?.map(String) || [],
    periods: params.periods?.map(String) || [],
    dateRange:
      params.dateRange && params.dateRange[0] && params.dateRange[1]
        ? [dayjs(params.dateRange[0]).toDate(), dayjs(params.dateRange[1]).toDate()]
        : [dayjs().toDate(), dayjs().toDate()],
  });

  const exportToExcel = () => {
    const searchParams = new URLSearchParams();

    if (form.data.games.length) form.data.games.forEach(g => searchParams.append('games[]', g));
    if (form.data.periods.length)
      form.data.periods.forEach(p => searchParams.append('periods[]', p));
    if (form.data.groups.length)
      form.data.groups.forEach(gr => searchParams.append('groups[]', gr));
    if (form.data.dateRange[0] && form.data.dateRange[1]) {
      searchParams.append('dateRange[0]', dayjs(form.data.dateRange[0]).format('YYYY-MM-DD'));
      searchParams.append('dateRange[1]', dayjs(form.data.dateRange[1]).format('YYYY-MM-DD'));
    }

    window.location.href = `/reports/export-projects?${searchParams.toString()}`;
  };

  // ✅ FUNCIÓN 1: Descargar página actual

  const downloadAllPdfsAsZip = async () => {
    try {
      setDownloadingZip(true);

      const projectIds = items.data.map(item => item.id);

      const response = await axios.post(
        route('projects.download.all.pdfs'),
        { ids: projectIds },
        {
          responseType: 'blob',
          timeout: 300000,
        }
      );

      // 👇 Leer mensaje (SIN base64 ahora)
      const downloadInfo = response.headers['x-download-info'];

      // Descargar ZIP
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `proyectos_${new Date().getTime()}.zip`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      // Mostrar mensaje DESPUÉS de descargar
      if (downloadInfo) {
        alert('⚠️ ' + downloadInfo);
      } else {
        alert('✅ Todos los proyectos se descargaron correctamente');
      }
    } catch (error) {
      console.error('Error:', error);

      if (error.response?.data instanceof Blob) {
        const text = await error.response.data.text();
        try {
          const errorData = JSON.parse(text);
          alert('❌ ' + (errorData.message || errorData.error));
        } catch {
          alert('❌ ' + text);
        }
      } else {
        alert('❌ ' + (error.response?.data?.error || error.message));
      }
    } finally {
      setDownloadingZip(false);
    }
  };

  const downloadAllFilteredPdfs = async () => {
    try {
      setDownloadingAllFiltered(true);

      // PASO 1: Obtener TODOS los IDs usando los mismos filtros del form
      const idsResponse = await axios.post(route('projects.get.all.filtered.ids'), {
        games: form.data.games || [],
        periods: form.data.periods || [],
        groups: form.data.groups || [],
        dateRange:
          form.data.dateRange && form.data.dateRange[0] && form.data.dateRange[1]
            ? [
                dayjs(form.data.dateRange[0]).format('YYYY-MM-DD'),
                dayjs(form.data.dateRange[1]).format('YYYY-MM-DD'),
              ]
            : null,
      });

      // console.log('🔴 === DEBUG FRONTEND INICIADO ===');
      // console.log('🔴 form.data completo:', form.data);
      // console.log('🔴 form.data.dateRange RAW:', form.data.dateRange);
      // console.log('🔴 items.total (esperado):', items.total);

      const allIds = idsResponse.data.ids;

      if (allIds.length === 0) {
        alert('No hay proyectos para descargar con los filtros actuales');
        return;
      }

      // Confirmar si son muchos
      if (allIds.length > 50) {
        const confirmar = confirm(
          `¿Seguro que quieres descargar ${allIds.length} PDFs? Puede tomar varios minutos.`
        );
        if (!confirmar) return;
      }

      // PASO 2: Descargar usando el método que YA funciona
      const response = await axios.post(
        route('projects.download.all.pdfs'),
        { ids: allIds },
        {
          responseType: 'blob',
          timeout: 600000, // 10 minutos
        }
      );

      // PASO 3: Descargar el ZIP
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `todos_proyectos_${new Date().getTime()}.zip`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      alert(`¡Descarga completa! ${allIds.length} proyectos descargados 🎉`);
    } catch (error) {
      if (error.response?.data instanceof Blob) {
        const text = await error.response.data.text();
        alert('Error del servidor: ' + text);
      } else {
        alert('Error: ' + (error.response?.data?.error || error.message));
      }
    } finally {
      setDownloadingAllFiltered(false);
    }
  };

  const findById = () => {
    if (!form.data.id) return; // No disparamos si está vacío

    form.get(route('reports.find-id'), {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        console.log('OT encontrada');
      },
      onError: errors => {
        console.error('Error en la búsqueda', errors);
      },
    });
  };

  return (
    <>
      <Breadcrumbs
        fz={14}
        mb={30}
      >
        <div>Reportes</div>
        <div>Buscar Ordenes de trabajo </div>
      </Breadcrumbs>

      <Title
        order={1}
        mb={20}
      >
        Buscar Ordenes de trabajo
      </Title>

      <ContainerBox
        px={35}
        py={25}
      >
        <form
          onSubmit={e => {
            e.preventDefault();
            // console.log('Datos que se envían:', form.data);
            submit(e);
          }}
        >
          <Group justify='space-between'>
            <Group gap='xl'>
              <MultiSelect
                placeholder={form.data.games.length ? null : 'Seleccionar atraccion '}
                w={220}
                value={form.data.games}
                onChange={values => updateValue('games', values)}
                data={games}
                error={form.errors.games}
              />

              <MultiSelect
                placeholder={form.data.periods.length ? null : 'Seleccionar periodo'}
                w={220}
                value={form.data.periods}
                onChange={values => updateValue('periods', values)}
                data={periods}
                error={form.errors.periods}
              />

              <MultiSelect
                placeholder={form.data.groups.length ? null : 'Seleccionar grupo'}
                w={220}
                value={form.data.groups}
                onChange={values => updateValue('groups', values)}
                data={[
                  { value: '2', label: 'Proceso' },
                  { value: '3', label: 'Revision' },
                  { value: '5', label: 'Sin iniciar' },
                  { value: '4', label: 'Finalizado' },
                ]}
                error={form.errors.groups}
              />

              <DatesProvider settings={{ timezone: 'America/Lima' }}>
                <DatePickerInput
                  type='range'
                  valueFormat='MMM D'
                  placeholder='Elija el rango de fechas'
                  clearable
                  allowSingleDateInRange
                  miw={200}
                  value={form.data.dateRange}
                  onChange={dates => {
                    console.log('Fechas seleccionadas:', dates);

                    updateValue('dateRange', dates);
                  }}
                />
              </DatesProvider>
            </Group>

            <Button
              type='submit'
              disabled={form.processing}
            >
              Enviar
            </Button>
          </Group>
        </form>

        <Group
          justify='flex-end'
          align='flex-end'
          mt='xl'
          gap='sm'
        >
          <TextInput
            placeholder='Buscar por ID (OT)'
            label='Búsqueda rápida' // Opcional: añade un label para separar visualmente
            w={200}
            value={form.data.id}
            onChange={e => updateValue('id', e.currentTarget.value)}
            error={form.errors.id}
          />

          <Button
            type='button' // IMPORTANTE: Cambiar a 'button' para que no dispare el submit del form
            onClick={findById}
            disabled={form.processing || !form.data.id}
          >
            Buscar OT
          </Button>

          {/* 👇 BOTONES A LA DERECHA - TODOS JUNTOS */}

          <div style={{ flex: 1 }} />

          <Button
            variant='outline'
            onClick={exportToExcel}
            disabled={!items.data || items.data.length === 0}
            leftSection={<IconDownload size={16} />}
          >
            Exportar a Excel
          </Button>

          <Button
            onClick={downloadAllPdfsAsZip}
            loading={downloadingZip}
            disabled={!items.data || items.data.length === 0 || downloadingZip}
            leftIcon={
              downloadingZip ? (
                <Loader
                  size={16}
                  color='white'
                />
              ) : (
                <IconDownload />
              )
            }
            color='teal'
          >
            {downloadingZip ? 'Generando ZIP...' : `Descargar (${items.data.length})`}
          </Button>
        </Group>
      </ContainerBox>

      <Box mt='xl'>
        {items.data && items.data.length ? (
          <>
            {/* {console.log('Items recibidos:', items.data)}
            {console.log('Total de items:', items.total)}
            {console.log('Items sin type_id:', items.data.filter(item => !item.type_id).length)}
            {console.log('Total de registros encontrados:', items.total)} */}

            <Flex
              mt='xl'
              gap='lg'
              justify='flex-start'
              align='flex-start'
              direction='row'
              wrap='wrap'
            >
              {items.data.map(item => (
                <ProjectCard
                  item={item}
                  key={item.id}
                />
              ))}
            </Flex>

            {/* Bloque de paginación */}
            <Group
              mt='lg'
              justify='center'
            >
              {items.links.map((link, i) => (
                <Button
                  key={i}
                  variant={link.active ? 'filled' : 'light'}
                  disabled={!link.url}
                  onClick={() => link.url && Inertia.get(link.url)}
                >
                  <span dangerouslySetInnerHTML={{ __html: link.label }} />
                </Button>
              ))}
            </Group>
          </>
        ) : (
          <Center mih={300}>
            <EmptyWithIcon
              title='No se encontró ninguna orden de trabajo'
              subtitle='Intente cambiar los filtros seleccionados'
              icon={IconClock}
            />
          </Center>
        )}
      </Box>
    </>
  );
};

SearchProject.layout = page => <Layout title='Buscar Orden de trabajo'>{page}</Layout>;

export default SearchProject;
