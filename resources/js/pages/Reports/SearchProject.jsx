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

import { IconDownload } from '@tabler/icons-react';

import ProjectCard from './ProjectCard';

/* Esta cosa manda mi informacion al backend */

const SearchProject = () => {
  let { items, games, periods } = usePage().props;

  const params = currentUrlParams();

  const [form, submit, updateValue] = useForm('get', route('reports.search-projects'), {
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
            console.log('Datos que se envían:', form.data);
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
          mt='md'
        >
          <Button
            variant='outline'
            onClick={exportToExcel}
            disabled={!items.data || items.data.length === 0}
            leftSection={<IconDownload size={16} />}
          >
            Exportar a Excel
          </Button>
        </Group>
      </ContainerBox>

      <Box mt='xl'>
        {items.data && items.data.length ? (
          <>

            {console.log('Items recibidos:', items.data)}
            {console.log('Total de items:', items.total)}
            {console.log('Items sin type_id:', items.data.filter(item => !item.type_id).length)}
            {console.log('Total de registros encontrados:', items.total)}

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
