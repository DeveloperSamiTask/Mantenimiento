import RoleBadge from "@/components/RoleBadge";
import TableRowActions from "@/components/TableRowActions";
import { money } from "@/utils/currency";
import { getInitials } from "@/utils/user";
import { Avatar, Flex, Group, Table, Text } from "@mantine/core";

export default function TableRow({ item }) {

  return (
    <Table.Tr key={item.id}>
      <Table.Td>
        <Group gap="sm">
          <Avatar
            src={item.avatar}
            size={40}
            radius={40}
            color="blue"
            alt={item.name}
          >
            {getInitials(item.name)}
          </Avatar>
          <div>
            <Text fz="sm" fw={500}>
              {item.name}
            </Text>
            <Text fz="xs" c="dimmed">
              {item.job_title}
            </Text>
          </div>
        </Group>
      </Table.Td>
      <Table.Td maw={200}>
        <Flex gap="sm" align="center" direction="row" wrap="wrap">
          {item.roles.map((role, index) => (
            <RoleBadge role={role} key={`role-${index}-${item.id}`} />
          ))}
        </Flex>
      </Table.Td>
      <Table.Td>
        <Text fz="sm">{item.email}</Text>
        <Text fz="xs" c="dimmed">
          Correo Electrónico
        </Text>
      </Table.Td>
      {can("ver tarifa de usuario") && (
        <Table.Td>
          <Text fz="sm">{money(item.rate)} / hr</Text>
          <Text fz="xs" c="dimmed">
            Tarifa
          </Text>
        </Table.Td>
      )}

      {(can("editar usuario") || can("archivar usuario") || can("restaurar usuario")) && (
        <Table.Td>
          <TableRowActions
            item={item}
            editRoute="users.edit"
            editPermission="editar usuario"
            archivePermission="archivar usuario"
            restorePermission="restaurar usuario"
            archive={{
              route: "users.destroy",
              title: "Archivar usuario",
              content: `¿Está seguro de que desea archivar este usuario? Esta acción evitará
                el usuario inicie sesión, mientras que todos los demás aspectos relacionados con el
                Las acciones del usuario no se verán afectadas.`,
              confirmLabel: "Archivar",
            }}
            restore={{
              route: "users.restore",
              title: "Restaurar usuario",
              content: `¿Estás seguro de que deseas restaurar este usuario? Esta acción permitirá al usuario iniciar sesión.`,
              confirmLabel: "Restaurar",
            }}
          />
        </Table.Td>
      )}
    </Table.Tr>
  );
}
