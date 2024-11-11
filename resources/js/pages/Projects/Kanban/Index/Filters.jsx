import useProjectGroupsStore from "@/hooks/store/useProjectGroupsStore";
import useProjectFiltersStore from "@/hooks/store/useProjectFiltersStore";
import { usePage } from "@inertiajs/react";
import { ColorSwatch, Stack, Text } from "@mantine/core";
import FilterButton from "./Filters/FilterButton";
import { DateInput, DatePickerInput } from "@mantine/dates";
import dayjs from "dayjs";

export default function Filters() {
  const { usersWithAccessToProject, labels , periods } = usePage().props;

  const { groups } = useProjectGroupsStore();
  const { filters, toggleArrayFilter, toggleObjectFilter, toggleValueFilter } =
    useProjectFiltersStore();

  return (
    <>
      <Stack justify="flex-start" gap={24}>
        {groups.length > 0 && (
          <div>
            <Text fz="xs" fw={700} tt="uppercase" mb="sm">
              Grupo de ordenes de trabajo
            </Text>
            <Stack justify="flex-start" gap={6}>
              {groups.map((item) => (
                <FilterButton
                  key={item.id}
                  selected={filters.groups.includes(item.id)}
                  onClick={() => toggleArrayFilter("groups", item.id)}
                >
                  {item.name}
                </FilterButton>
              ))}
            </Stack>
          </div>
        )}

        {periods.length > 0 && (
          <div>
            <Text fz="xs" fw={700} tt="uppercase" mb="sm">
              Grupo de periodos
            </Text>
            <Stack justify="flex-start" gap={6}>
              {periods.map((item) => (
                <FilterButton
                  key={item.id}
                  selected={filters.periods.includes(item.id)}
                  onClick={() => toggleArrayFilter("periods", item.id)}
                >
                  {item.name}
                </FilterButton>
              ))}
            </Stack>
          </div>
        )}

          <div>
          <Text fz="xs" fw={700} tt="uppercase" mb="sm">
            Tipo
          </Text>
          <Stack justify="flex-start" gap={6}>
            <FilterButton
              selected={filters.fault_date == "fault_date"}
              onClick={() => toggleValueFilter("fault_date", "fault_date")}
            >
              Hoja de falla
            </FilterButton>
          </Stack>
        </div>

        <div>
          <Text fz="xs" fw={700} tt="uppercase" mb="sm">
            Fecha
          </Text>
          <Stack justify="flex-start" gap={6}>
            <DatePickerInput
              clearable
              // type="range"
              valueFormat="DD MMM YYYY"
              placeholder="Elija la fecha de la OT"
              value={filters.date ? dayjs(filters.date).toDate() : null}
              onChange={value => {
                const formattedDate = value ? dayjs(value).format('YYYY-MM-DD') : null;
                toggleValueFilter("date", formattedDate);
              }}
            />
          </Stack>
        </div>

        <div>
          <Text fz="xs" fw={700} tt="uppercase" mb="sm">
            Fecha de vencimiento
          </Text>
          <Stack justify="flex-start" gap={6}>
            <FilterButton
              selected={filters.due_date.not_set == 1}
              onClick={() => toggleObjectFilter("due_date", "not_set")}
            >
              No establecido
            </FilterButton>
            <FilterButton
              selected={filters.due_date.overdue == 1}
              onClick={() => toggleObjectFilter("due_date", "overdue")}
            >
              Vencido
            </FilterButton>
          </Stack>
        </div>

        <div>
          <Text fz="xs" fw={700} tt="uppercase" mb="sm">
            Estado
          </Text>
          <Stack justify="flex-start" gap={6}>
            <FilterButton
              selected={filters.status === "completed"}
              onClick={() => toggleValueFilter("status", "completed")}
            >
              Completado
            </FilterButton>
          </Stack>
        </div>

        {labels.length > 0 && (
          <div>
            <Text fz="xs" fw={700} tt="uppercase" mb="sm">
              Etiquetas
            </Text>
            <Stack justify="flex-start" gap={6}>
              {labels.map((item) => (
                <FilterButton
                  key={item.id}
                  selected={filters.labels.includes(item.id)}
                  onClick={() => toggleArrayFilter("labels", item.id)}
                  leftSection={<ColorSwatch color={item.color} size={18} />}
                >
                  {item.name}
                </FilterButton>
              ))}
            </Stack>
          </div>
        )}
      </Stack>
    </>
  );
}
