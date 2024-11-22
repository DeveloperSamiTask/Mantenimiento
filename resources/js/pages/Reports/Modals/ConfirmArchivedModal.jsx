import useForm from "@/hooks/useForm";
import { Button, Flex, Text, TextInput } from "@mantine/core";
import { modals } from "@mantine/modals";

function ModalForm({idProject}) {
  const [form, submit, updateValue] = useForm(
    "delete",
    route("projects.kanban.destroy", idProject),
    { motive_archived: "" },
  );

  const submitModal = (event) => {
    submit(event, {
      onSuccess: () => modals.closeAll(),
      preserveScroll: true,
    });
  };

  return (
    <form onSubmit={submitModal}>
      <Text>¿Estás seguro de que deseas archivar esta OT?</Text>
      <TextInput
        label="Motivo"
        placeholder="Escriba el motivo de la cancelacion de la OT"
        mt="md"
        required
        data-autofocus
        value={form.data.motive_archived}
        onChange={(e) => updateValue("motive_archived", e.target.value)}
        error={form.errors.motive_archived}
      />

      <Flex justify="flex-end" mt="xl">
        <Button type="submit" w={100} color="red" loading={form.processing}>
          Archivar
        </Button>
      </Flex>
    </form>
  );
}

const ConfirmArchivedModal = (idProject) => {
  modals.open({
    title: (
      <Text size="xl" fw={700} mb={-10}>
        Archivar OT
      </Text>
    ),
    centered: true,
    size: "lg",
    padding: "xl",
    overlayProps: { backgroundOpacity: 0.55, blur: 3 },
    children: <ModalForm idProject = {idProject} />,
  });
};

export default ConfirmArchivedModal;
