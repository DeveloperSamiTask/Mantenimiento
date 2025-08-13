import { Label } from "@/components/Label";
import useProjectDrawerStore from "@/hooks/store/useProjectDrawerStore";
import { isOverdue } from "@/utils/project";
import { getInitials } from "@/utils/user";
import { Draggable } from "@hello-pangea/dnd";
import { Link } from "@inertiajs/react";
import { Avatar, Checkbox, Group, Loader, LoadingOverlay, Text, Tooltip, rem, useComputedColorScheme } from "@mantine/core";
import ProjectActions from "../ProjectActions";
import classes from "./css/ProjectCard.module.css";
import useProjectsStore from "@/hooks/store/useProjectsStore";
import { useState } from "react";

export default function   ProjectCard({ project, index }) {
  const { openEditProject } = useProjectDrawerStore();
  const computedColorScheme = useComputedColorScheme();
  const { toggleProjectSelection, selectedProjects } = useProjectsStore();
  const [isClicked, setIsClicked] = useState(false);

  const handleCheckboxChange = () => {
    toggleProjectSelection(project);
  };
  return (
    <Draggable draggableId={"project-" + project.id} index={index}>

      {(provided, snapshot) => (
        <div
          {...provided.draggableProps}
          ref={provided.innerRef}
          className={`${classes.project} ${snapshot.isDragging && classes.itemDragging} ${
            project.completed_at != null && classes.completed
          }`}
        >

          <div {...(can("reordenar proyecto") && provided.dragHandleProps)}>
          <Group wrap="nowrap">

            <LoadingOverlay visible={isClicked} loaderProps={{ children: <Loader size={40} /> }} />

            {(can("reordenar proyecto")) && (
              <Checkbox
                checked={selectedProjects.some(p => p.id == project.id)}
                key={project.id}
                display={project.completed_at != null ? 'none' : 'inline'}
                color="rgba(79, 79, 79, 79)"
                onChange={handleCheckboxChange}
              />
            )}

            <Text
              className={classes.name}
              size="xs"
              fw={500}
              c={isOverdue(project) && project.completed_at == null ? "red.7" : ""}
              onClick={(e) => {
                e.stopPropagation();
                if(can('editar proyecto') || project.default != 1){
                  setIsClicked(true);
                  openEditProject(project);
                  setTimeout(() => setIsClicked(false), 300);
                }
              }}
            >
              #{project.id + ": " + project.name}
            </Text>
          </Group>

            <Group wrap="nowrap" justify="space-between">
              <Group wrap="wrap" style={{ rowGap: rem(3), columnGap: rem(12) }} mt={5}>
                {project.labels.map((label) => (
                  <Label
                    key={label.id}
                    name={label.name}
                    color={label.color}
                    size={9}
                    dot={false}
                  />
                ))}
              </Group>

            {(can("archivar proyecto") || can("restaurar proyecto") || can("descargar proyecto")) && (
                <ProjectActions project={project} className={classes.actions} />
              )}
            </Group>

            <Group wrap="nowrap" justify="space-between">
              {project.default == 1 && (
                <div className="flex flex-col gap-2 items-center">
                  <Text c="dimmed" fz="sm" mt="md">
                    Total de tareas:{" "}
                    <Text span fw={500} c="bright">
                      {project.check_list_count}
                    </Text>
                  </Text>
                  <Text c="red" fz="sm">
                    Ultima programacion: {project.due_on ? new Date(project.due_on).toLocaleDateString() : "No programada"}
                  </Text>
                </div>
              ) || project.default != 1 && (
                <Text c="dimmed" fz="sm" mt="md">
                  Tareas completadas:{" "}
                <Text span fw={500} c="bright">
                  {project.completed_tasks_count} / {project.all_tasks_count}
                </Text>
                </Text>
              )}
            </Group>
          </div>
        </div>
      )}
    </Draggable>
  );
}
