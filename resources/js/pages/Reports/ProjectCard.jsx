import { stopOnIgnoreLink } from "@/utils/domEvents";
import { getInitials } from "@/utils/user";
import { Link } from "@inertiajs/react";
import { Avatar, Card, Group, Progress, rem, Text, Tooltip } from "@mantine/core";
import classes from "./css/ProjectCard.module.css";
import { Label } from "@/components/Label";

export default function ProjectCard({ item }) {

  return (
    <Link
      href={item.default != 1 && can("ver proyecto") ? route("projects.tasks", item.id) : route("projects.kanban")}
      className={classes.link}
      onClick={stopOnIgnoreLink}
    >
      <Card withBorder padding="xl" radius="md" w={350} className={classes.card}>
        <Group justify="space-between">
          <Text fz={23} fw={700} className={item.default != 1 ? classes.title : ''}>
            {item.name}
          </Text>
        </Group>

        <Text fz="md" fw={800}>
          {item.default == 1 ? 'Defecto' : ''}
        </Text>

        {item.description?.length > 0 && (
          <Text fz="sm" c="dimmed" mt="lg">
            {item.description}
          </Text>
        )}

          <Group wrap="wrap" style={{ rowGap: rem(3), columnGap: rem(12) }} mt={10}>
            {item.labels.map((label) => (
              <Label
                key={label.id}
                name={label.name}
                color={label.color}
                size={10}
                dot={false}
              />
            ))}
        </Group>

        <Text c="dimmed" fz="sm" mt="md">
          Total de tareas:{" "}
          <Text span fw={500} c="bright">
            {item.tasks ? item.tasks.length : 0}
          </Text>
        </Text>

        <Group justify="space-between" mt="md">
          <Avatar.Group spacing="sm">
            {item.users.slice(0, 4).map((user) => (
              <Tooltip key={user.id} label={user.name} openDelay={300} withArrow>
                <Avatar
                  src={user.avatar}
                  radius="xl"
                  style={{ cursor: "default" }}
                  data-ignore-link
                  className={classes.avatar}
                >
                  {getInitials(user.name)}
                </Avatar>
              </Tooltip>
            ))}
            {item.users.length - 4 > 0 && (
              <Avatar radius="xl">+{item.users.length - 4}</Avatar>
            )}
          </Avatar.Group>

        </Group>
      </Card>

    </Link>


  );
}
