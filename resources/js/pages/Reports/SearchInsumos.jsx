import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { MultiSelect, TextInput, Button, Group, Title, Flex, Center, Box } from '@mantine/core';
import { DatePickerInput } from '@mantine/dates';
import { DatesProvider } from '@mantine/dates';
import { IconSearch } from '@tabler/icons-react';
import { Inertia } from '@inertiajs/inertia';
import dayjs from 'dayjs';
import useForm from '@/hooks/useForm';
// import { currentUrlParams } from '@/utils/currentUrlParams';
import { currentUrlParams } from '@/utils/route';
import Layout from '@/layouts/MainLayout';
import ContainerBox from '@/layouts/ContainerBox';
import InsumoCard from './InsumoCard';

const SearchInsumos = () => {
  const { items, games, periods } = usePage().props;
  const params = currentUrlParams();

  const [form, submit, updateValue] = useForm('get', route('insumos.search'), {
    ot_id: params.ot_id || '',
    game_id: params.game_id?.map(String) || [],
    period_id: params.period_id?.map(String) || [],
    dateRange:
      params.due_on_start && params.due_on_end
        ? [dayjs(params.due_on_start).toDate(), dayjs(params.due_on_end).toDate()]
        : [null, null],
  });

  return (
    <>
      <Title
        order={1}
        mb={20}
      >
        Insumos por OT
      </Title>

      <ContainerBox
        px={35}
        py={25}
      >
        <form
          onSubmit={e => {
            e.preventDefault();
            submit(e);
          }}
        >
          <Group justify='space-between'>
            <Group gap='xl'>
              <MultiSelect
                placeholder='Atracción'
                w={220}
                value={form.data.game_id}
                onChange={v => updateValue('game_id', v)}
                data={games}
              />

              <MultiSelect
                placeholder='Periodo'
                w={220}
                value={form.data.period_id}
                onChange={v => updateValue('period_id', v)}
                data={periods}
              />

              <DatesProvider settings={{ timezone: 'America/Lima' }}>
                <DatePickerInput
                  type='range'
                  valueFormat='MMM D'
                  placeholder='Rango de fechas'
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
              Buscar
            </Button>
          </Group>

          {/* Búsqueda rápida por ID de OT */}
          <Group
            justify='flex-end'
            mt='md'
          >
            <TextInput
              placeholder='Buscar por ID de OT'
              w={200}
              value={form.data.ot_id}
              onChange={e => updateValue('ot_id', e.currentTarget.value)}
            />
            <Button
              type='button'
              leftSection={<IconSearch size={16} />}
              disabled={!form.data.ot_id || form.processing}
              onClick={() => submit()}
            >
              Buscar OT
            </Button>
          </Group>
        </form>
      </ContainerBox>

      <Box mt='xl'>
        {items.data && items.data.length ? (
          <>
            <Flex
              mt='xl'
              gap='lg'
              justify='flex-start'
              align='flex-start'
              direction='row'
              wrap='wrap'
            >
              {items.data.map(item => (
                <InsumoCard
                  item={item}
                  key={item.id}
                />
              ))}
            </Flex>

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
            <div>No se encontraron resultados</div>
          </Center>
        )}
      </Box>
    </>
  );
};

SearchInsumos.layout = page => <Layout title='Insumos por OT'>{page}</Layout>;
export default SearchInsumos;
