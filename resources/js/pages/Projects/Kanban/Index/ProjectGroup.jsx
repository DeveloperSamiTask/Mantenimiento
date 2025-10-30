import { Draggable, Droppable } from '@hello-pangea/dnd';
import {
  ActionIcon,
  Checkbox,
  Group,
  Loader,
  LoadingOverlay,
  Text,
  Tooltip,
  rem,
} from '@mantine/core';
import { IconGripVertical, IconSend2 } from '@tabler/icons-react';
import Project from './Project';
import ProjectGroupActions from './ProjectGroupActions';
import classes from './css/ProjectGroup.module.css';
import useProjectsStore from '@/hooks/store/useProjectsStore';
import { useState } from 'react';
import AccesUsersModal from './Modals/AccesUsersModal';
import { usePage } from '@inertiajs/react';
import { openConfirmModal } from '@/components/ConfirmModal';
import { Button } from "@mantine/core";
/*
  Es un componente React que representa un grupo de proyectos dentro de un tablero tipo Kanban.
  cada columna puede convertir varios proyectos (tarjetas) ,

*/

//me interesa el group -> "pendiente" , proyectos que pertenecen a ese grupo ,
export default function ProjectGroup({ group, projectsGroup, ...props }) {
  const { projects, selectedProjects, moveSelectedProjects, setProjects } = useProjectsStore();

  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);

  const loadMore = async () => {
    setLoadingMore(true);
    try {
      const offset = projects[group.id].length; // Cuántos ya tienes
      const response = await axios.get(
        route('projects.kanban.loadMore', group.id) + `?offset=${offset}`
      );

      // Agregar los nuevos proyectos al estado
      setProjects({
        ...projects,
        [group.id]: [...projects[group.id], ...response.data],
      });
    } catch (error) {
      console.error('Error cargando más proyectos:', error);
    }
    setLoadingMore(false);
  };

  const disabledAction = () => {
    if (selectedProjects.length == 0) {
      return false;
    }
    return selectedProjects.every(p => p.group_id == group.id); // Verifica si todos los IDs de grupo son iguales
  };

  const assignedUsers = () => {
    const projectDefault = selectedProjects.every(p => p.default == 1);
    const projectFinalize = selectedProjects.every(p => p.group_id == 3);
    if (projectDefault) {
      return AccesUsersModal(setLoading);
    }
    if (projectFinalize) {
      return openConfirmModal({
        type: 'info',
        title: 'Finalizar Orden de Trabajo',
        content: `¿Estás seguro de que deseas finalizar esta orden de trabajo?`,
        confirmLabel: 'Finalizar',
        confirmProps: { color: 'blue' },
        onConfirm: () => moveSelectedProjects(selectedProjects, setLoading, null),
      });
    }
    moveSelectedProjects(selectedProjects, setLoading, null);
  };

  return (
    <Draggable
      draggableId={group.id.toString()}
      {...props}
    >
      {(provided, snapshot) => (
        <div
          className={`${classes.row} ${snapshot.isDragging && classes.itemDragging}`}
          ref={provided.innerRef}
          {...provided.draggableProps}
        >
          <LoadingOverlay
            visible={loading}
            loaderProps={{ children: <Loader size={40} /> }}
          />

          <div className={classes.group}>
            <Group>
              <div
                {...provided.dragHandleProps}
                className={classes.dragHandle}
              >
                {/* <IconGripVertical
                  style={{
                    width: rem(18),
                    height: rem(18),
                    display:
                      can("reordenar grupo de proyecto") && !route().params.archived ? "inline" : "none",
                  }}
                  stroke={1.5}
                /> */}
              </div>
              <Text
                size='xl'
                fw={700}
              >
                {group.name} {projects[group.id].length}
              </Text>
              {/* <ProjectGroupActions group={group} className={classes.actions} /> */}
            </Group>
            {!route().params.archived && group.id != 4 && can('reordenar proyecto') && (
              <Tooltip
                label='Mover tareas'
                openDelay={1000}
                withArrow
              >
                <ActionIcon
                  variant='filled'
                  size='md'
                  radius='xl'
                  onClick={() => assignedUsers()}
                  disabled={!disabledAction()}
                >
                  <IconSend2
                    style={{ width: rem(18), height: rem(18) }}
                    stroke={2}
                  />
                </ActionIcon>
              </Tooltip>
            )}
          </div>
          <Droppable
            droppableId={`group-${group.id}-projects`}
            type='project'
          >
            {(provided, snapshot) => (
              <div
                ref={provided.innerRef}
                {...provided.droppableProps}
                className={snapshot.isDraggingOver ? 'isDraggingOver' : ''}
                style={{
                  maxHeight: '550px',
                  overflowY: 'auto',
                }}
              >
                {projectsGroup.map((project, index) => (
                  <Project
                    key={project.id}
                    project={project}
                    index={index}
                  />
                ))}


                {projectsGroup.length > 0 && (
                  <Button
                    onClick={loadMore}
                    loading={loadingMore}
                    variant='subtle'
                    fullWidth
                    mt='sm'
                    size='sm'
                  >
                    Cargar más proyectos
                  </Button>
                )}

                <div className={classes.placeholder}>{provided.placeholder}</div>
              </div>
            )}
          </Droppable>
        </div>
      )}
    </Draggable>
  );
}
