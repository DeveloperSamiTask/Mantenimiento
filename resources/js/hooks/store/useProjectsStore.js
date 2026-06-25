import createProjectAttachmentsSlice from '@/hooks/store/projects/ProjectAttachmentsSlice';
import createProjectCommentsSlice from '@/hooks/store/projects/ProjectCommentsSlice';
import createProjectTimeLogsSlice from '@/hooks/store/projects/ProjectTimeLogsSlice';
import createProjectWebSocketUpdatesSlice from '@/hooks/store/projects/ProjectWebSocketUpdatesSlice';
import { move, reorder } from '@/utils/reorder';
import { notifications } from '@mantine/notifications';
import axios from 'axios';
import { produce } from "immer";
import { create } from 'zustand';

const useProjectsStore = create((set, get) => ({
  ...createProjectAttachmentsSlice(set, get),
  ...createProjectTimeLogsSlice(set, get),
  ...createProjectCommentsSlice(set, get),
  ...createProjectWebSocketUpdatesSlice(set, get),

  projects: {},
  selectedProjects: [],
  setProjects: (projects) => set(() => ({ projects: { ...projects } })),
  addProject: (project) => {
    return set(produce(state => {
      const index = state.projects[project.group_id].findIndex((i) => i.id === project.id);

      if (index === -1) {
        state.projects[project.group_id] = [...state.projects[project.group_id], project];
      }
    }));
  },

  findProject: (id) => {
    for (const groupId in get().projects) {
      const project = get().projects[groupId].find((i) => i.id === id);

      if (project) {
        return project;
      }
    }
    return null;
  },

  updateProjectProperty: async (project, property, value, options = null) => {
    try {
      if (property != 'completed_tasks_count' && options != 'updateLabels') {
        await axios
          .put(
            route("projects.kanban.update", [project.id]),
            { [property]: value },
            { progress: false },
          )
      }

      if (options == 'updateLabels') {
        options = value;
      }

      return set(produce(state => {
        const index = state.projects[project.group_id].findIndex((i) => i.id == project.id);

        if (property == 'group_id' && project.group_id != value) {
          const result = move(state.projects, project.group_id, value, index, 0);

          state.projects[project.group_id] = result[project.group_id];
          state.projects[value] = result[value];

          state.projects[value][0][property] = value;
        } else {
          state.projects[project.group_id][index][property] = options || value;
        }
      }));

    } catch (e) {
      console.error(e);
      alert("Failed to save project property change");
    }
  },

  complete: (project, checked) => {
    const newState = checked ? true : null;
    const index = get().projects[project.group_id].findIndex((i) => i.id == project.id);

    axios
      .post(route("projects.kanban.complete", [project.id]), { completed: checked })
      .catch(() => alert("Failed to save project completed action"));

    return set(produce(state => {
      state.projects[project.group_id][index].completed_at = newState
    }));
  },

  reorderProject: (source, destination) => {
    const sourceGroupId = +source.droppableId.split("-")[1];

    const result = reorder(get().projects[sourceGroupId], source.index, destination.index);

    const data = {
      ids: result.map((i) => i.id),
      group_id: sourceGroupId,
      from_index: source.index,
      to_index: destination.index,
    };

    axios
      .post(route("projects.kanban.reorder", [route().params.project]), data, { progress: false }) // Revisar el params.proyecto
      .catch(() => alert("Failed to save project reorder action"));

    return set(produce(state => { state.projects[sourceGroupId] = result }));
  },

  moveProject: async (source, destination, setLoading) => {
    setLoading(true);
    const sourceGroupId = +source.droppableId.split("-")[1];
    const destinationGroupId = +destination.droppableId.split("-")[1];

    const result = move(get().projects, sourceGroupId, destinationGroupId, source.index, destination.index);
    const project = result[destinationGroupId][destination.index];
    const data = {
      ids: result[destinationGroupId].map((i) => i.id),
      from_group_id: sourceGroupId,
      to_group_id: destinationGroupId,
      from_index: source.index,
      to_index: destination.index,
    };

    const check = project.tasks.some(task => task.check == null);
    if (project && (destinationGroupId == 1 || project.tasks.length == 0 || check || project.completed_at != null || sourceGroupId == 4 || project.default == 1)) {
      setLoading(false);
      return notifications.show({
        title: 'Acción denegada',
        message: 'No se puede mover esta orden de trabajo',
        radius: 'md',
        color: 'orange',
        autoClose: 2000,
      });
    }

    await axios
      .post(route("projects.kanban.move", [route().params.project]), data, { progress: false }) // revisar el params.proyect
      .then(response => {
        const idsLabel = [project.group_id, 7];
        const labels = response.data.filter(item => idsLabel.includes(item.id));
        get().clearSelectedProjects();
        get().updateProjectProperty(project, 'labels', [project.group_id, 7], labels); // Para actualizar los labels
      })
      .catch(() => alert("No se pudo guardar la acción de movimiento del proyecto"));

    set(produce(state => {
      state.projects[sourceGroupId] = result[sourceGroupId];
      state.projects[destinationGroupId] = result[destinationGroupId];
    }));

    if (destinationGroupId == 4) {
      setLoading(false);
      return get().complete(project, true);
    }
    return setLoading(false);
  },

  toggleProjectSelection: (project) => {
    set(produce(state => {
      const index = state.selectedProjects.findIndex(p => p.id == project.id);
      if (index !== -1) {
        state.selectedProjects.splice(index, 1);
      } else {
        state.selectedProjects.push(project);
      }
    }));
  },

  moveSelectedProjects: async (projects, setLoading, accessUsers, insumos = []) => {
    setLoading(true);
    let canMove = false;

    for (const project of projects) {

      const check = project.tasks.some(task => task.check == null);
      canMove = project && project.default != 1 && (project.tasks.length == 0 || check || project.completed_at != null);

      if (canMove) {
        notifications.show({
          title: 'Acción denegada',
          message: 'No se puede mover esta orden de trabajo',
          radius: 'md',
          color: 'orange',
          autoClose: 2000,
        });
        continue;
      }

      if (project.default == 1 && accessUsers != null) {

        const newProject = {
          ...project,
          name: project.name + ' ' + '(' + accessUsers.due_on + ')',
          created_at: null,
          updated_at: null,
          default: 0,
          type_id: 1,
          id: null,
          labels: [],
          users: accessUsers.users,
          due_on: accessUsers.due_on,
          number: null,
          order_column: null,
        }

        try {
          const response = await axios.post(
            route("projects.kanban.moveSelectedProjects"),  // ✅
            newProject,
            { progress: false }
          );

          const otCreada = response.data;
          get().addProject(otCreada);

          // 2. Con el id de la OT creada, crea OTInsumo + insumos
          if (insumos.length > 0) {
            await axios.post(
              route('insumos.store'),
              {
                ot_id: otCreada.id,
                due_on: accessUsers.due_on,
                game_id: project.game_id,    // viene de la plantilla
                period_id: project.period_id,  // viene de la plantilla
                insumos: insumos,
              },
              { progress: false }
            );
          }
          get().addProject(response.data);
        } catch {
          alert("No se puede crear la orden de trabajo");
        }
        continue;
      }

      const sourceGroupId = project.group_id;
      const destinationGroupId = Number(project.group_id) + 1;
      const sourceIndex = Object.values(get().projects[sourceGroupId]).findIndex(p => p.id == project.id);
      const destinationIndex = get().projects[destinationGroupId].length;

      const result = move(get().projects, sourceGroupId, destinationGroupId, sourceIndex, destinationIndex);
      const projectMoved = result[destinationGroupId][destinationIndex];

      const data = {
        ids: [project.id],
        from_group_id: sourceGroupId,
        to_group_id: destinationGroupId,
        from_index: sourceIndex,
        to_index: destinationIndex,
      };

      await axios
        .post(route("projects.kanban.move", [route().params.project]), data, { progress: true })
        .then(response => {
          const idsLabel = [projectMoved.group_id, 7];
          const labels = response.data.filter(item => idsLabel.includes(item.id));
          get().updateProjectProperty(projectMoved, 'labels', [projectMoved.group_id, 7], labels); // Para actualizar los labels
        });

      set(produce(state => {
        state.projects[sourceGroupId] = result[sourceGroupId];
        state.projects[destinationGroupId] = result[destinationGroupId];
      }));

      if (destinationGroupId == 4) get().complete(projectMoved, true);

    };
    get().clearSelectedProjects();
    setLoading(false);
  },

  clearSelectedProjects: () => set({ selectedProjects: [] }),

}));

export default useProjectsStore;
