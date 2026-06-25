import { Button, Text, Group, Flex } from '@mantine/core';

export function InsumosSelectorModal({ selectedProjects, formData, onConfirm }) {
  return (
    <Flex direction="column" gap="md">
      <Text size="sm" color="dimmed">
        Administra y asigna los insumos requeridos para los proyectos seleccionados.
      </Text>

      <div style={{
        border: '1px dashed #e0e0e0',
        borderRadius: '8px',
        padding: '20px',
        backgroundColor: '#f9f9f9',
        minHeight: '150px'
      }}>
        <Text size="xs" color="gray" fontStyle="italic">
          La información ya está lista en segundo plano.
        </Text>
      </div>

      <Group position="right" mt="xl">
        <Button variant="outline" color="gray" onClick={() => modals.closeAll()}>
          Cancelar
        </Button>
        <Button color="blue" onClick={onConfirm}>
          Confirmar Selección y Cerrar Todo
        </Button>
      </Group>
    </Flex>
  );
}

export default InsumosSelectorModal;
