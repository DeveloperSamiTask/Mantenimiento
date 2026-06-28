import { openConfirmModal } from '@/components/ConfirmModal';
import useForm from '@/hooks/useForm';
import { router } from '@inertiajs/react';
import { ActionIcon, Menu, rem, Loader } from '@mantine/core';
import {
  IconArchive,
  IconArchiveOff,
  IconDots,
  IconFileDownload,
  IconPencil,
  IconUsers,
} from '@tabler/icons-react';
import ConfirmArchivedModal from './Modals/ConfirmArchivedModal.jsx';
import { useState } from 'react';

export default function ProjectCardActions({ item }) {
  const [restoreForm] = useForm('post', route('projects.restore', item.id));
  const [loading, setLoading] = useState(false);

  const openArchiveModal = () => ConfirmArchivedModal(item.id);

  const openRestoreModal = () =>
    openConfirmModal({
      type: 'info',
      title: 'Restaurar OT',
      content: `¿Estás seguro de que deseas restaurar esta OT?`,
      confirmLabel: 'Restaurar',
      confirmProps: { color: 'blue' },
      onConfirm: () => restoreForm.submit({ preserveScroll: true }),
    });

 const openPdfProject = async () => {
    try {
      setLoading(true);
      const response = await axios.get(route('projects.kanban.pdf', item.id), {
        responseType: 'blob',
      });

      const urlPdf = window.URL.createObjectURL(response.data);
      window.open(urlPdf, '_blank');
    } catch (e) {
      console.error('Error descargando PDF', e);
    } finally {
      setLoading(false);
    }
  };

  const openUserAccess = () => UserAccessModal(item);

  return (
    <>
      {(can('editar acceso usuario al proyecto') ||
        can('editar proyecto') ||
        can('restaurar proyecto') ||
        can('archivar proyecto') ||
        can('descargar proyecto')) && (
        <Menu
          withArrow
          position='bottom-end'
          shadow='md'
          transitionProps={{ duration: 100, transition: 'pop-top-right' }}
          offset={{ mainAxis: 3, alignmentAxis: 5 }}
          data-ignore-link
        >
          <Menu.Target>
            <ActionIcon
              variant='subtle'
              color='gray'
              data-ignore-link
            >
              <IconDots
                style={{ width: rem(20), height: rem(20) }}
                stroke={1.5}
                data-ignore-link
              />
            </ActionIcon>
          </Menu.Target>
          <Menu.Dropdown>
            {can('editar proyecto') && item.default != 1 && (
              <Menu.Item
                leftSection={
                  <IconPencil
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                onClick={() => router.visit(route('projects.edit', item.id))}
                data-ignore-link
              >
                Editar
              </Menu.Item>
            )}

            <Menu.Item
              leftSection={
                loading ? (
                  <Loader
                    size={16}
                    color='teal'
                  />
                ) : (
                  <IconFileDownload
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                )
              }
              color='teal'
              onClick={openPdfProject}
              disabled={loading}
              data-ignore-link
            >
              {loading ? 'Descargando...' : 'Descargar'}
            </Menu.Item>

            {can('restaurar proyecto') && route().params.archived && (
              <Menu.Item
                leftSection={
                  <IconArchiveOff
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                color='blue'
                onClick={openRestoreModal}
                data-ignore-link
              >
                Restaurar
              </Menu.Item>
            )}
            {can('archivar proyecto') && !route().params.archived && (
              <Menu.Item
                leftSection={
                  <IconArchive
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                color='red'
                onClick={openArchiveModal}
                data-ignore-link
              >
                Archivar
              </Menu.Item>
            )}
          </Menu.Dropdown>
        </Menu>
      )}
    </>
  );
}
