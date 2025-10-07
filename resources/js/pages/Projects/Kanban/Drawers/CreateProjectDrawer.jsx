import { openConfirmModal } from '@/components/ConfirmModal';
import useProjectDrawerStore from '@/hooks/store/useProjectDrawerStore';
import useForm from '@/hooks/useForm';
import { usePage } from '@inertiajs/react';
import dayjs from 'dayjs';
import {
  Button,
  Checkbox,
  Drawer,
  Flex,
  MultiSelect,
  NumberInput,
  Select,
  TagsInput,
  Text,
  TextInput,
  Textarea,
  rem,
} from '@mantine/core';
import { DateInput, DateTimePicker } from '@mantine/dates';
import { useEffect, useState } from 'react';
import LabelsDropdown from './LabelsDropdown';
import classes from './css/ProjectDrawer.module.css';

export function CreateProjectDrawer() {
  const { create, closeCreateProject } = useProjectDrawerStore();
  const {
    // usersWithAccessToProject,
    users_access,
    games,
    labels,
    types,
    periods,
    auth: { user },
  } = usePage().props;

  const [isFaultSheet, setIsFaultSheet] = useState(false);
  const initial = {
    client_company_id: 1,
    group_id: create.group_id ? create.group_id.toString() : 1,
    game_id: '',
    period_id: '',
    type_id: '',
    name: '',
    description: '',
    rate: 0,
    estimation: '',
    due_on: '',
    start_date: '',
    fault_date: '',
    default: false,
    labels: [],
    users: [],
    tasks: [],
  };

  const [form, submit, updateValue] = useForm(
    'post',
    route('projects.kanban.store', [route().params.project]),
    {
      ...initial,
    }
  );

  const handleFaulSheetChange = event => {
    setIsFaultSheet(event.currentTarget.checked);
  };

  useEffect(() => {
    setIsFaultSheet(false);
    updateValue({ ...initial });
  }, [create.opened]);

  const closeDrawer = (force = false) => {
    if (force || (JSON.stringify(form.data) === JSON.stringify(initial) && !form.processing)) {
      closeCreateProject();
    } else {
      openConfirmModal({
        type: 'danger',
        title: '¿Descartar cambios?',
        content: `Todos los cambios no guardados se perderán.`,
        confirmLabel: 'Desechar',
        confirmProps: { color: 'red' },
        onConfirm: () => closeCreateProject(),
      });
    }
  };

  //  console.log('Todas las props:', usePage().props);

  return (
    <Drawer
      opened={create.opened}
      onClose={closeDrawer}
      title={
        <Text
          fz={rem(28)}
          fw={600}
          ml={25}
          my='sm'
        >
          Agregar {!isFaultSheet ? 'orden de trabajo' : 'hoja de falla'}
        </Text>
      }
      position='right'
      size={1000}
      overlayProps={{ backgroundOpacity: 0.55, blur: 3 }}
      transitionProps={{
        transition: 'slide-left',
        duration: 400,
        timingFunction: 'ease',
      }}
    >
      <form
        onSubmit={event =>
          submit(event, {
            onSuccess: () => closeDrawer(true),
            forceFormData: true,
          })
        }
        className={classes.inner}
      >
        <div className={classes.content}>
          <TextInput
            label='Nombre'
            placeholder='Nombre de la orden de trabajo'
            required
            data-autofocus
            value={form.data.name}
            onChange={e => updateValue('name', e.target.value)}
            error={form.errors.name}
          />

          <Textarea
            label='Descripción'
            placeholder='Descripción de la orden de trabajo'
            mt='md'
            autosize
            minRows={isFaultSheet ? 10 : 15}
            maxRows={20}
            value={form.data.description}
            onChange={e => updateValue('description', e.target.value)}
          />

          {isFaultSheet && (
            <TagsInput
              label='Tareas programadas'
              mt='md'
              placeholder='Ingresar tareas de la hoja de falla'
              value={form.data.tasks}
              onChange={values => updateValue('tasks', values)}
            />
          )}

          <MultiSelect
            label='Conceder acceso a las usuarias'
            placeholder='Seleccionar usuarios'
            searchable
            mt='md'
            value={form.data.users}
            onChange={values => updateValue('users', values)}
            data={users_access.map(i => ({
              value: i.id.toString(),
              label: i.name,
            }))}
            error={form.errors.users}
          />

          <Flex
            justify='space-between'
            mt='xl'
          >
            <Button
              variant='transparent'
              w={100}
              disabled={form.processing}
              onClick={closeDrawer}
            >
              Cancelar
            </Button>

            <Button
              type='submit'
              w={120}
              loading={form.processing}
            >
              Agregar
            </Button>
          </Flex>
        </div>
        <div className={classes.sidebar}>
          <Select
            label='Atracción que requiere mantenimiento'
            placeholder='Seleccionar atracción'
            searchable
            value={form.data.game_id}
            onChange={value => updateValue('game_id', value)}
            data={games}
            error={form.errors.game_id}
          />

          {!isFaultSheet && (
            <Select
              label='Tipo de mantenimiento'
              placeholder='Seleccionar tipo de mantenimiento'
              searchable
              mt='md'
              value={form.data.type_id}
              onChange={value => updateValue('type_id', value)}
              data={types}
              error={form.errors.type_id}
            />
          )}

          <Select
            label='Período de mantenimiento'
            placeholder='Seleccionar período'
            searchable
            mt='md'
            value={form.data.period_id}
            onChange={value => updateValue('period_id', value)}
            data={periods.map(period => ({
              value: period.id.toString(),
              label: period.name,
            }))}
            error={form.errors.period_id}
          />

          <DateInput
            clearable
            valueFormat='DD MMM YYYY'
            minDate={new Date()}
            mt='md'
            label='Fecha de vencimiento'
            placeholder='Elija la fecha de vencimiento de la OT'
            value={form.data.due_on ? new Date(form.data.due_on) : null}
            onChange={value => {
              const formattedDate = value ? dayjs.tz(value, 'America/Lima').format() : null;
              updateValue('due_on', formattedDate);
            }}
          />

          {isFaultSheet && (
            <>
              <DateTimePicker
                clearable
                valueFormat='DD MMM YYYY hh:mm A'
                label='Hora de falla'
                placeholder='Elija la fecha y hora de la falla'
                mt='md'
                value={form.data.fault_date ? new Date(form.data.fault_date) : null}
                onChange={value => {
                  const formattedDate = value ? dayjs.tz(value, 'America/Lima').format() : null;
                  updateValue('fault_date', formattedDate);
                }}
              />
              <DateTimePicker
                clearable
                valueFormat='DD MMM YYYY hh:mm A'
                label='Hora de inicio'
                placeholder='Elija la fecha y hora de inicio'
                mt='md'
                value={form.data.start_date ? new Date(form.data.start_date) : null}
                onChange={value => {
                  const formattedDate = value ? dayjs.tz(value, 'America/Lima').format() : null;
                  updateValue('start_date', formattedDate);
                }}
              />
            </>
          )}

          <LabelsDropdown
            items={labels}
            selected={form.data.labels}
            onChange={values => updateValue('labels', values)}
            mt='md'
          />

          <NumberInput
            label={!isFaultSheet ? 'Estimación de tiempo' : 'Tiempo fuera de servicio'}
            mt='md'
            decimalScale={2}
            fixedDecimalScale
            defaultValue={0}
            min={0}
            allowNegative={false}
            step={0.1}
            suffix=' hours'
            onChange={value => updateValue('estimation', value)}
          />

          <Checkbox
            label='¿Es una hoja de falla?'
            description='Seleccione si desea agregar una hoja de falla'
            mt='xl'
            checked={isFaultSheet}
            onChange={handleFaulSheetChange}
          />
        </div>
      </form>
    </Drawer>
  );
}
