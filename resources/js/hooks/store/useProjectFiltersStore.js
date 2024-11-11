import { currentUrlParams, reloadWithQuery, reloadWithoutQueryParams } from '@/utils/route';
import { produce } from "immer";
import isArray from 'lodash/isArray';
import omit from 'lodash/omit';
import { create } from 'zustand';

const params = currentUrlParams();

const useProjectFiltersStore = create((set, get) => ({
  openedDrawer: false,
  filters: {
    groups: params.groups || [],
    periods: params.periods || [],
    assignees: params.assignees || [],
    date: null,
    due_date: {
      not_set: params.not_set || 0,
      overdue: params.overdue || 0,
    },
    fault_date: params.fault_date || 0,
    status: params.status || 0,
    labels: params.labels || [],
  },
  hasUrlParams: (exclude = []) => {
    const params = omit(currentUrlParams(), exclude);

    return Object.keys(params).length > 0;
  },
  hasFilters: () => {
    const filters = get().filters;
    const keys = Object.keys(filters);

    return keys.some((key) => {
      if (isArray(filters[key])) {
        return filters[key].length > 0;
      } else {
        const keys = Object.keys(filters[key]);
        return keys.some((k) => !!filters[key][k]);
      }
    });
  },
  clearFilters: () => {
    reloadWithoutQueryParams({keep: ['archive']});

    return set(() => ({
      filters: {
        groups: [],
        periods: [],
        assignees: [],
        date: '',
        due_date: {
          not_set: 0,
          overdue: 0,
        },
        status: 0,
        fault_date: 0,
        labels: [],
      }
    }));
  },
  toggleArrayFilter: (field, id) => {
    return set(
      produce((state) => {
        const index = state.filters[field].findIndex((i) => i === id);

        if (index !== -1) {
          state.filters[field].splice(index, 1);
        } else {
          state.filters[field].push(id);
        }
        reloadWithQuery({ [field]: state.filters[field] }, true);
      }),
    );
  },
  toggleObjectFilter: (field, property) => {
    return set(
      produce((state) => {
        if (state.filters[field][property] === 0) {
          state.filters[field][property] = 1;
          reloadWithQuery({ [property]: 1 }, true);
        } else {
          state.filters[field][property] = 0;
          reloadWithoutQueryParams({exclude: [property]});
        }
      }),
    );
  },
  toggleValueFilter: (field, value) => {
    return set(
      produce((state) => {
        // Verificar si el filtro ya tiene un valor
        if (state.filters[field] !== value) {
          state.filters[field] = value; // Si es diferente, actualizamos el filtro
          reloadWithQuery({ [field]: value }, true); // Recargamos la consulta con el nuevo valor
        } else {
          state.filters[field] = null; // Limpiamos el filtro si el valor es el mismo
          reloadWithoutQueryParams({ exclude: [field] }); // Recargamos sin el campo excluido
        }
      }),
    );
  },
  openDrawer: () => {
    return set(produce(state => {state.openedDrawer = true}));
  },
  closeDrawer: () => {
    return set(produce(state => {state.openedDrawer = false}));
  },
}));

export default useProjectFiltersStore;
