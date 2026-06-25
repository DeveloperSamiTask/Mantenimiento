import { Button, Flex, MultiSelect, Skeleton, Text, TextInput } from '@mantine/core';
import { modals } from '@mantine/modals';
import { useEffect, useRef, useState } from 'react';
import useForm from '@/hooks/useForm';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { hasRoles } from '@/utils/user';
import useProjectsStore from '@/hooks/store/useProjectsStore';
import { DateInput } from '@mantine/dates';
import InsumosSelectorModal from './InsumosSelectorModal';

function ModalForm({ setLoading }) {
  const [requestPending, setRequestPending] = useState(true);
  const { selectedProjects, moveSelectedProjects } = useProjectsStore();
  const [users, setUsers] = useState([]);
  const [form, submit, updateValue] = useForm({
    users: [], // Inicializa el valor de usuarios
  });

  const submitModal = event => {
    event.preventDefault();

    const formData = {
      users: form.data.users.map(i => i.toString()),
      due_on: form.data.due_on.toISOString().split('T')[0] || '',
    };

    modals.closeAll(); // cierra el modal actual

    // Abre el segundo modal pasando todo
    modals.open({
      fullScreen: true,
      title: (
        <Text
          size='xl'
          fw={700}
        >
          Selección de Insumos
        </Text>
      ),
      centered: true,
      size: '90%',
      padding: 'xl',
      overlayProps: { backgroundOpacity: 0.55, blur: 3 },
      children: (
        <InsumosSelectorModal
          selectedProjects={selectedProjects}
          setLoading={setLoading}
          formData={formData}
        />
      ),
    });
  };

  useEffect(() => {
    axios
      .get(route('dropdown.values', ['users']))
      .then(({ data }) => {
        setUsers([...data.users]);
      })
      .catch(() => alert('Algo salió mal, no se pudieron cargar los valores del menú desplegable'))
      .finally(() => setRequestPending(false));
  }, [form.data]);

  return (
    <>
      <form onSubmit={submitModal}>
        {requestPending ? (
          <>
            <Skeleton
              height={10}
              width={50}
              mt={8}
              radius='xl'
            />
            <Skeleton
              height={25}
              mt={10}
              radius='xl'
            />

            <Skeleton
              height={10}
              width={50}
              mt={25}
              radius='xl'
            />
            <Skeleton
              height={25}
              mt={10}
              radius='xl'
            />

            <Skeleton
              height={10}
              width={50}
              mt={25}
              radius='xl'
            />
            <Skeleton
              height={25}
              mt={10}
              radius='xl'
            />
          </>
        ) : (
          <>
            <MultiSelect
              label='Usuarios'
              placeholder='Seleccionar usuarios'
              searchable
              required
              value={requestPending ? [] : form.data.users}
              onChange={values => updateValue('users', values)}
              data={users}
              error={form.errors.users}
            />
            <DateInput
              clearable
              valueFormat='DD MMM YYYY'
              minDate={new Date()}
              required
              mt='md'
              label='Fecha de vencimiento'
              placeholder='Elija la fecha de vencimiento de la OT'
              value={form.data.due_on ? new Date(form.data.due_on) : null}
              onChange={value => updateValue('due_on', value)}
            />
          </>
        )}

        <Flex
          justify='flex-end'
          mt='xl'
        >
          <Button
            type='submit'
            w={100}
            disabled={requestPending || !form.data.users}
            loading={form.processing}
          >
            Guardar
          </Button>
        </Flex>
      </form>
    </>
  );
}

const AccesUsersModal = setLoading => {
  modals.open({
    title: (
      <Text
        size='xl'
        fw={700}
        mb={-10}
      >
        Acceso a los usuarios
      </Text>
    ),
    centered: true,
    padding: 'xl',
    overlayProps: { backgroundOpacity: 0.55, blur: 3 },
    children: <ModalForm setLoading={setLoading} />,
  });
};

export default AccesUsersModal;
