import ArchivedFilterButton from '@/components/ArchivedFilterButton';
import Pagination from '@/components/Pagination';
import SearchInput from '@/components/SearchInput';
import TableHead from '@/components/TableHead';
import TableRowEmpty from '@/components/TableRowEmpty';
import ContainerBox from '@/layouts/ContainerBox';
import Layout from '@/layouts/MainLayout';
import { redirectTo, reloadWithQuery } from '@/utils/route';
import { actionColumnVisibility, prepareColumns } from '@/utils/table';
import { currentUrlParams } from '@/utils/route';
import { usePage } from '@inertiajs/react';
import useForm from '@/hooks/useForm';
import {
  Box,
  Button,
  Center,
  Checkbox,
  Grid,
  Group,
  MultiSelect,
  Table,
  Text,
  Title,
} from '@mantine/core';
import { IconPlus } from '@tabler/icons-react';
import TableRow from './TableRow';

const CheckListIndex = () => {
  let { items, dropdowns } = usePage().props;
  const params = currentUrlParams();

  const [form, submit, updateValue] = useForm('get', route('attractions.checklists.index'), {
    games: params.games?.map(String) || [],
    periods: params.periods?.map(String) || [],
  });

  const columns = prepareColumns([
    { label: 'Nombre', column: 'name' },
    { label: 'Atracción', column: 'game_id' },
    { label: 'Periodo', column: 'period_id' },
    { label: 'Imagen', column: 'archive' },
    {
      label: 'Acciones',
      sortable: false,
      visible: actionColumnVisibility('checklist'), // roles
    },
  ]);

  const rows = items.data.length ? (
    items.data.map(item => (
      <TableRow
        item={item}
        key={item.id}
      />
    ))
  ) : (
    <TableRowEmpty colSpan={columns.length} />
  );

  const search = search => reloadWithQuery({ search });
  const sort = sort => reloadWithQuery(sort);

  return (
    <>
      <Title
        order={1}
        mb={20}
      >
        Lista de checklist
      </Title>

      <ContainerBox
        px={35}
        py={25}
      >
        <form onSubmit={submit}>
          <Group justify='space-between'>
            <Group gap='xl'>
              <MultiSelect
                placeholder={form.data.games.length ? null : 'Seleccionar atracciones'}
                required
                w={400}
                value={form.data.games}
                onChange={values => updateValue('games', values)}
                data={dropdowns.games}
                error={form.errors.games}
              />

              <MultiSelect
                placeholder={form.data.periods.length ? null : 'Seleccionar periodos'}
                required
                w={300}
                value={form.data.periods}
                onChange={values => updateValue('periods', values)}
                data={dropdowns.periods}
                error={form.errors.periods}
              />
            </Group>

            <Button
              type='submit'
              disabled={form.processing}
            >
              Buscar
            </Button>
            {can('crear checklist') && ( // roles
              <Button
                leftSection={<IconPlus size={14} />}
                radius='xl'
                onClick={() => redirectTo('attractions.checklists.create')}
              >
                Crear checklist
              </Button>
            )}
          </Group>
        </form>
      </ContainerBox>

      <Box mt='xl'>
        <Grid
          justify='space-between'
          align='center'
        >
          <Grid.Col span='content'>
            <Group>
              <SearchInput
                placeholder='Buscar checklists'
                search={search}
              />
              <ArchivedFilterButton />
            </Group>
          </Grid.Col>
        </Grid>

        <Table.ScrollContainer
          miw={800}
          my='lg'
        >
          <Table verticalSpacing='sm'>
            <TableHead
              columns={columns}
              sort={sort}
            />
            <Table.Tbody>{rows}</Table.Tbody>
          </Table>
        </Table.ScrollContainer>

        <Pagination
          current={items.meta.current_page}
          pages={items.meta.last_page}
        />
      </Box>
    </>
  );
};

CheckListIndex.layout = page => <Layout title='Check List'>{page}</Layout>;

export default CheckListIndex;
