import { openConfirmModal } from "@/components/ConfirmModal";
import useForm from "@/hooks/useForm";
import { router } from "@inertiajs/react";
import { ActionIcon, Menu, rem } from "@mantine/core";
import { IconArchive, IconArchiveOff, IconDots, IconFileDownload, IconPencil, IconUsers } from "@tabler/icons-react";
import ConfirmArchivedModal from "./Modals/ConfirmArchivedModal.jsx";

export default function ProjectCardActions({ item }) {
  const [restoreForm] = useForm("post", route("projects.restore", item.id));

  const openArchiveModal = () => ConfirmArchivedModal(item.id);

  const openRestoreModal = () =>
    openConfirmModal({
      type: "info",
      title: "Restaurar OT",
      content: `¿Estás seguro de que deseas restaurar esta OT?`,
      confirmLabel: "Restaurar",
      confirmProps: { color: "blue" },
      onConfirm: () => restoreForm.submit({ preserveScroll: true }),
    });

    const openPdfProject = async () => {
      const response = await axios.get(route("projects.kanban.pdf", item.id), {responseType:"blob"});
      const urlPdf = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
      window.open(urlPdf, "_blanck");
    };

  const openUserAccess = () => UserAccessModal(item);

  return (
    <>
      {(can("editar acceso usuario al proyecto") ||
        can("editar proyecto") ||
        can("restaurar proyecto") ||
        can("archivar proyecto")) && (
        <Menu
          withArrow
          position="bottom-end"
          shadow="md"
          transitionProps={{ duration: 100, transition: "pop-top-right" }}
          offset={{ mainAxis: 3, alignmentAxis: 5 }}
          data-ignore-link
        >
          <Menu.Target>
            <ActionIcon variant="subtle" color="gray" data-ignore-link>
              <IconDots style={{ width: rem(20), height: rem(20) }} stroke={1.5} data-ignore-link />
            </ActionIcon>
          </Menu.Target>
          <Menu.Dropdown>
            {can("editar proyecto") && item.default != 1 && (
              <Menu.Item
                leftSection={
                  <IconPencil
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                onClick={() => router.visit(route("projects.edit", item.id))}
                data-ignore-link
              >
                Editar
              </Menu.Item>
            )}
              <Menu.Item
                leftSection={
                  <IconFileDownload style={{ width: rem(16), height: rem(16) }} stroke={1.5} />
                }
                color="teal"
                onClick={openPdfProject}
              >
                Descargar
              </Menu.Item>

            {can("restaurar proyecto") && route().params.archived && (
              <Menu.Item
                leftSection={
                  <IconArchiveOff
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                color="blue"
                onClick={openRestoreModal}
                data-ignore-link
              >
                Restaurar
              </Menu.Item>
            )}
            {can("archivar proyecto") && !route().params.archived && (
              <Menu.Item
                leftSection={
                  <IconArchive
                    style={{ width: rem(16), height: rem(16) }}
                    stroke={1.5}
                    data-ignore-link
                  />
                }
                color="red"
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
