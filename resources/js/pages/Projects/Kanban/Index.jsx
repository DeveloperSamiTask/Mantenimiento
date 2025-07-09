import { EmptyResult } from "@/components/EmptyResult";
import useProjectFiltersStore from "@/hooks/store/useProjectFiltersStore";
import useProjectGroupsStore from "@/hooks/store/useProjectGroupsStore";
import useProjectsStore from "@/hooks/store/useProjectsStore";
import useProjectPreferences from "@/hooks/useProjectPreferences";
import useWebSockets from "@/hooks/useWebSockets";
import Layout from "@/layouts/MainLayout";
import { DragDropContext, Droppable } from "@hello-pangea/dnd";
import { usePage } from "@inertiajs/react";
import { Button, Grid, Loader, LoadingOverlay } from "@mantine/core";
import { IconPlus } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { CreateProjectDrawer } from "./Drawers/CreateProjectDrawer";
import { EditProjectDrawer } from "./Drawers/EditProjectDrawer";
import ArchivedItems from "./Index/Archive/ArchivedItems";
import Filters from "./Index/Filters";
import FiltersDrawer from "./Index/FiltersDrawer";
import Header from "./Index/Header";
import CreateProjectsGroupModal from "./Index/Modals/CreateProjectsGroupModal";
import ProjectGroup from "./Index/ProjectGroup";
import classes from "./css/Index.module.css";


const KanbanIndex = () => {
  const { projectGroups, groupedProjects, openedProject } = usePage().props;
  const [loading, setLoading] = useState  (false);

  const { groups, setGroups, reorderGroup } = useProjectGroupsStore();
  const { projects, setProjects, addProject, reorderProject, moveProject, clearSelectedProjects } = useProjectsStore();
  const { hasUrlParams } = useProjectFiltersStore();
  const { initProjectWebSocket } = useWebSockets();
  const { projectsView } = useProjectPreferences();

  const usingFilters = hasUrlParams();

  useEffect(() => {
    setGroups(projectGroups);
    setProjects(groupedProjects);
    if (openedProject) addProject(openedProject);
  }, [projectGroups, groupedProjects]);

  useEffect(() => {
    initProjectWebSocket(openedProject);
    clearSelectedProjects();
    return initProjectWebSocket();
  }, []);

  const onDragEnd = ({ source, destination }) => {
    if (!destination) {
      return;
    }
    if (source.droppableId.includes("projects") && destination.droppableId.includes("projects")) {
      if (source.droppableId === destination.droppableId) {
        reorderProject(source, destination);
      } else {
        moveProject(source, destination, setLoading);
      }
    } else {
      reorderGroup(source.index, destination.index);
    }
  };

  return (
    <>
      <Header />

      {can("crear proyecto") && <CreateProjectDrawer />}
      <EditProjectDrawer />

      <LoadingOverlay visible={loading} loaderProps={{ children: <Loader size={40} /> }} />

      <Grid columns={12} gutter={50} mt="xl" className={`${projectsView}-view`}>
        {!route().params.archived ? (
          <Grid.Col span={projectsView === "list" ? 9 : 12}>
            {groups.length ? (
              <>
                <DragDropContext onDragEnd={onDragEnd}>
                  <Droppable
                    droppableId="groups"
                    direction={projectsView === "list" ? "vertical" : "horizontal"}
                    type="group"
                  >
                    {(provided) => (
                      <div {...provided.droppableProps} ref={provided.innerRef}>
                        <div className={classes.viewport}>
                          {groups
                            .filter(
                              (group) =>
                                !usingFilters || (usingFilters && projects[group.id]?.length > 0),
                            )
                            .map((group, index) => (
                              <ProjectGroup
                                key={group.id}
                                index={index}
                                group={group}
                                projectsGroup={projects[group.id] || []}
                              />
                            ))}
                          {provided.placeholder}
                          {/* {!route().params.archived && can("crear grupo de proyecto") && (
                            <Button
                              leftSection={<IconPlus size={14} />}
                              variant="transparent"
                              size="sm"
                              mt={14}
                              m={4}
                              radius="xl"
                              onClick={CreateProjectsGroupModal}
                              style={{ width: "200px" }}
                            >
                              Agregar {projectsView === "list" ? "grupo de OTs" : "grupo"}
                            </Button>
                          )} */}
                        </div>
                      </div>
                    )}
                  </Droppable>
                </DragDropContext>
              </>
            ) : (
              <EmptyResult title="No se encontraron ordenes de trabajo" subtitle="o ninguno coincide con sus criterios de búsqueda" />
            )}
          </Grid.Col>
        ) : (
          <Grid.Col span={projectsView === "list" ? 9 : 12}>
            <ArchivedItems groups={groups} projects={projects} />
          </Grid.Col>
        )}
        {projectsView === "list" ? (
          <Grid.Col span={3}>
            <Filters />
          </Grid.Col>
        ) : (
          <FiltersDrawer />
        )}
      </Grid>
    </>
  );
};

KanbanIndex.layout = (page) => <Layout title="Kanban">{page}</Layout>;

export default KanbanIndex;
