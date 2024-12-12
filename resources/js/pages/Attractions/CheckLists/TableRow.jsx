import { openConfirmModal } from '@/components/ConfirmModal';
import TableRowActions from '@/components/TableRowActions';
import { useForm } from "laravel-precognition-react-inertia";
import { ActionIcon, Badge, Group, Menu, rem, Table, Text } from '@mantine/core';
import { IconCheck, IconX } from '@tabler/icons-react';

export default function TableRow({ item }) {
  const permitImagenForm = useForm("post", route('attractions.checklists.editImage', [item.id, 1]));
  const omiteImageForm = useForm("post", route('attractions.checklists.editImage', [item.id, 0]));

  const openOmitModal = () =>
    openConfirmModal({
      type: "danger",
      title: 'Desea deshabilitar imagenes obligatoriamente',
      content: '¿Está seguro de que desea realizar esta accion?',
      confirmLabel: 'Aceptar',
      confirmProps: { color: "red" },
      onConfirm: () => omiteImageForm.submit(),
    });

  const openPermitModal = () =>
    openConfirmModal({
      type: "info",
      title: 'Desea habilitar imagenes obligatoriamente',
      content: '¿Está seguro de que desea realizar esta accion?',
      confirmLabel: 'Aceptar',
      confirmProps: { color: "blue" },
      onConfirm: () => permitImagenForm.submit(),
    });

  return (
    <Table.Tr key={item.id}>
      <Table.Td>
        <Text
          fz='sm'
          fw={500}
        >
          {item.name}
        </Text>
      </Table.Td>
      <Table.Td>
        <Text
          fz='sm'
          fw={500}
        >
          {item.game_id.name}
        </Text>
        <Text
          fz='xs'
          c='dimmed'
        >
          Nombre de la atracción
        </Text>
      </Table.Td>
      <Table.Td>
        <Badge
          variant='light'
          color='cyan'
          tt='unset'
        >
          {item.period_id.name}
        </Badge>
      </Table.Td>
      <Table.Td>
        <Menu
          withArrow
          position='bottom-end'
          withinPortal
          shadow='md'
          transitionProps={{ duration: 100, transition: 'pop-top-right' }}
          offset={{ mainAxis: 3, alignmentAxis: 5 }}
        >
          <Menu.Target>
            <ActionIcon
              variant='subtle'
              color='gray'
            >
              {item.archive == 1 ? (
                <IconCheck
                  style={{ width: rem(25), height: rem(25) }}
                  color={item.archive == 1 ? 'green' : 'red'}
                  stroke={1.5}
                />
              ) : (
                <IconX
                  style={{ width: rem(25), height: rem(25) }}
                  color={item.archive == 1 ? 'green' : 'red'}
                  stroke={1.5}
                />
              )}
            </ActionIcon>
          </Menu.Target>
          <Menu.Dropdown>
            {item.archive == 1
              ? can('editar checklist') && (
                  <Menu.Item
                    leftSection={
                      <IconX
                        style={{ width: rem(16), height: rem(16) }}
                        stroke={1.5}
                      />
                    }
                    color='red'
                    onClick={openOmitModal}
                  >
                    Omitir imagenes
                  </Menu.Item>
                )
              : can('editar checklist') && (
                  <Menu.Item
                    leftSection={
                      <IconCheck
                        style={{ width: rem(16), height: rem(16) }}
                        stroke={1.5}
                      />
                    }
                    color='blue'
                    onClick={openPermitModal}
                  >
                    Permitir imagenes
                  </Menu.Item>
                )}
          </Menu.Dropdown>
        </Menu>
      </Table.Td>
      {(can('editar checklist') || can('archivar checklist') || can('restaurar checklist')) && (
        <Table.Td>
          <TableRowActions
            item={item}
            editRoute='attractions.checklists.edit'
            editPermission='editar checklist' // roles
            archivePermission='archivar checklist' // roles
            restorePermission='restaurar checklist' // roles
            archive={{
              route: 'attractions.checklists.destroy',
              title: 'Archivar ubicación',
              content: `¿Está seguro de que desea archivar este checklist?`,
              confirmLabel: 'Archivar',
            }}
            restore={{
              route: 'attractions.checklists.restore',
              title: 'Restaurar ubicación',
              content: `¿Está seguro de que desea restaurar este checklist?`,
              confirmLabel: 'Restaurar',
            }}
          />
        </Table.Td>
      )}
    </Table.Tr>
  );
}
