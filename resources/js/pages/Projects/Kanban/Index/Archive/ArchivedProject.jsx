import { Label } from "@/components/Label";
import { isOverdue } from "@/utils/project";
import { shortName } from "@/utils/user";
import { Link } from "@inertiajs/react";
import { Checkbox, Flex, Group, Pill, Text, Tooltip } from "@mantine/core";
import classes from "../Task/css/ProjectRow.module.css";
import ProjectActions from "../ProjectActions";

export default function ArchivedProject({ project }) {
  return (
    <Flex className={`${classes.project} ${project.completed_at !== null && classes.completed}`}>
      <Group gap="sm">
        <Checkbox
          size="sm"
          radius="xl"
          color="green"
          defaultChecked={project.completed_at !== null}
          className={classes.disabledCheckbox}
        />
        {project.assigned_to_user && (
          <Link href={route("users.edit", project.assigned_to_user.id)}>
            <Tooltip label={project.assigned_to_user.name} openDelay={1000} withArrow>
              <Pill size="sm" className={classes.user}>
                {shortName(project.assigned_to_user.name)}
              </Pill>
            </Tooltip>
          </Link>
        )}
        <Text
          key={project.id}
          className={classes.name}
          style={{ cursor: "default" }}
          size="sm"
          fw={500}
          c={isOverdue(project) && project.completed_at === null ? "red" : ""}
        >
          #{project.id + ": " + project.name}
        </Text>

        <Group gap={12} ml={8}>
          {project.labels.map((label) => (
            <Label key={label.id} name={label.name} color={label.color} />
          ))}
        </Group>

        <ProjectActions project={project} />
      </Group>
    </Flex>
  );
}
