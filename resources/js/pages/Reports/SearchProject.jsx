import EmptyWithIcon from '@/components/EmptyWithIcon';
import useForm from '@/hooks/useForm';
import ContainerBox from '@/layouts/ContainerBox';
import Layout from '@/layouts/MainLayout';
import { currentUrlParams } from '@/utils/route';
import { usePage } from '@inertiajs/react';
import {
  Box,
  Breadcrumbs,
  Button,
  Center,
  Checkbox,
  Flex,
  Group,
  MultiSelect,
  Table,
  Title,
} from '@mantine/core';
import { DatePickerInput, DatesProvider } from '@mantine/dates';
import { IconClock } from '@tabler/icons-react';
import dayjs from 'dayjs';
import { round } from 'lodash';
import ProjectCard from './ProjectCard';

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

  return (
    <>
      <Breadcrumbs
        fz={14}
        mb={30}
      >
        <div>Reportes</div>
        <div>Buscar Ordenes de trabajo</div>
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
        <form onSubmit={submit}>
          <Group justify='space-between'>
            <Group gap='xl'>


              <MultiSelect
                placeholder={form.data.games.length ? null : 'Seleccionar atraccion'}
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
                  onChange={dates => updateValue('dateRange', dates)}
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
      </ContainerBox>

      <Box mt='xl'>
        {Object.keys(items).length ? (
          <Flex mt="xl" gap="lg" justify="flex-start" align="flex-start" direction="row" wrap="wrap">
          {items.map((item) => (
            <ProjectCard item={item} key={item.id} />
          ))}
        </Flex>
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
