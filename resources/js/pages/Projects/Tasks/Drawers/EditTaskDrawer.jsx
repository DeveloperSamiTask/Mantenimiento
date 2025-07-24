import Dropzone from "@/components/Dropzone";
import RichTextEditor from "@/components/RichTextEditor";
import useTaskDrawerStore from "@/hooks/store/useTaskDrawerStore";
import useTasksStore from "@/hooks/store/useTasksStore";
import useWebSockets from "@/hooks/useWebSockets";
import { date } from "@/utils/datetime";
import { hasRoles } from "@/utils/user";
import { usePage } from "@inertiajs/react";
import {
  Box,
  Breadcrumbs,
  Checkbox,
  Drawer,
  Group,
  Loader,
  LoadingOverlay,
  MultiSelect,
  NumberInput,
  Select,
  Text,
  TextInput,
  rem,
} from "@mantine/core";
import { DateInput } from "@mantine/dates";
import dayjs from "dayjs";
import { useEffect, useRef, useState } from "react";
import Comments from "./Comments";
import LabelsDropdown from "./LabelsDropdown";
import Timer from "./Timer";
import classes from "./css/TaskDrawer.module.css";

export function EditTaskDrawer() {
  const editorRef = useRef(null);
  const { edit, openEditTask, closeEditTask, closeProjectKanban } = useTaskDrawerStore();
  const { initTaskWebSocket } = useWebSockets();
  const { findTask, updateTaskProperty, complete, deleteAttachment, uploadAttachments } =
    useTasksStore();
  const {
    usersWithAccessToProject,
    taskGroups,
    labels,
    openedTask,
    controle,
    typeChecks,
    auth: { user },
  } = usePage().props;

  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (openedTask) setTimeout(() => openEditTask(openedTask), 50);
  }, []);

  const task = findTask(edit.task.id);

  const [data, setData] = useState({
    group_id: "",
    assigned_to_user_id: "",
    name: "",
    check: "",
    type_check: "",
    description: "",
    estimation: 0,
    due_on: "",
    hidden_from_clients: false,
    sent_archive: false,
    billable: true,
    subscribed_users: [],
    labels: [],
  });

  useEffect(() => {
    if (edit.opened) {
      return initTaskWebSocket(task);
    }
  }, [edit.opened]);

  useEffect(() => {
    if (edit.opened) {
      setData({
        group_id: task?.group_id || "",
        assigned_to_user_id: task?.assigned_to_user_id || "",
        name: task?.name || "",
        check: task?.check || "",
        description: task?.description || "",
        estimation: task?.estimation || 0,
        type_check: task?.type_check || "",
        due_on: task?.due_on ? dayjs(task?.due_on).toDate() : "",
        hidden_from_clients: task?.hidden_from_clients !== undefined ? task.hidden_from_clients : false,
        billable: task?.billable !== undefined ? task.billable : true,
        sent_archive: task?.sent_archive !== undefined ? task.sent_archive : false,
        subscribed_users: (task?.subscribed_users || []).map((i) => i.id.toString()),
        labels: (task?.labels || []).map((i) => i.id),
      });
      editorRef.current?.setContent(task?.description || "");
    }
  }, [edit.opened, task]);

  const updateValue = (field, value) => {
    setData({ ...data, [field]: value });

    const dropdowns = ["labels", "subscribed_users"];
    const onBlurInputs = ["name", "description"];

    if (dropdowns.includes(field)) {
      const options = {
        labels: value.map((id) => labels.find((i) => i.id === id)),
        subscribed_users: value.map((id) =>
          usersWithAccessToProject.find((i) => i.id.toString() === id),
        ),
      };
      updateTaskProperty(task, field, value, options[field]);
    } else if (!onBlurInputs.includes(field)) {
      updateTaskProperty(task, field, value);
    }
  };

  const onBlurUpdate = (property) => {
    if (data.name.length > 0) {
      updateTaskProperty(task, property, data[property]);
    }
  };

  return (
    <Drawer
      opened={edit.opened}
      onClose={controle == 0 ? closeEditTask : closeProjectKanban}
      title={
        <Group ml={25} my="sm" wrap="nowrap">
          <Checkbox
            size="md"
            radius="xl"
            color="green"
            checked={task?.completed_at !== null}
            onChange={(e) => complete(task, e.currentTarget.checked)}
            className={can("completar tarea") ? classes.checkbox : classes.disabledCheckbox}
          />
          <Text
            fz={rem(27)}
            fw={600}
            lh={1.2}
            td={task?.completed_at !== null ? "line-through" : null}
          >
            #{task?.number}: {data.name}
          </Text>
        </Group>
      }
      position="right"
      size={1000}
      overlayProps={{ backgroundOpacity: 0.55, blur: 3 }}
      transitionProps={{
        transition: "slide-left",
        duration: 400,
        timingFunction: "ease",
      }}
    >
      {task ? (
        <>
          <LoadingOverlay visible={loading} loaderProps={{ children: <Loader size={40} /> }} />
          <Breadcrumbs
            c="dark.3"
            ml={24}
            mb="xs"
            separator="I"
            separatorMargin="sm"
            styles={{ separator: { opacity: 0.3 } }}
          >
            <Text size="xs">#{task.project.id} {task.project.name}</Text>
            <Text size="xs">Task #{task.number}</Text>
            <Text size="xs">
              Created by {task.created_by_user.name} on {date(task.created_at)}
            </Text>
          </Breadcrumbs>
          <form className={classes.inner}>
            <div className={classes.content}>
              <TextInput
                label="Nombre"
                placeholder="Nombre de la tarea"
                value={data.name}
                onChange={(e) => updateValue("name", e.target.value)}
                onBlur={() => onBlurUpdate("name")}
                error={data.name.length === 0}
                readOnly={!can("editar tarea")}
              />

              <RichTextEditor
                ref={editorRef}
                mt="xl"
                placeholder="Descripción de la tarea"
                content={data.description}
                height={260}
                onChange={(content) => updateValue("description", content)}
                onBlur={() => onBlurUpdate("description")}
                readOnly={!can("editar tarea")}
              />

              {can("editar tarea") && (
                <Dropzone
                  mt="xl"
                  selected={task.attachments}
                  onChange={(files) => uploadAttachments(task, files, setLoading)}
                  remove={(index) => deleteAttachment(task, index, setLoading)}
                />
              )}

              {can("ver comentarios") && <Comments task={task} />}
            </div>
            <div className={classes.sidebar}>
              <Select
                label="Grupo de la tarea"
                placeholder="Seleccionar grupo de la tarea"
                allowDeselect={false}
                value={data.group_id.toString()}
                onChange={(value) => updateValue("group_id", value)}
                data={taskGroups.map((i) => ({
                  value: i.id.toString(),
                  label: i.name,
                }))}
                readOnly={!can("editar tarea")}
              />

              <Select
                label="Asigando"
                placeholder="Seleccionar asignado"
                searchable
                mt="md"
                display="none"
                value={data.assigned_to_user_id?.toString()}
                onChange={(value) => updateValue("assigned_to_user_id", value)}
                data={usersWithAccessToProject.map((i) => ({
                  value: i.id.toString(),
                  label: i.name,
                }))}
                readOnly={!can("editar tarea")}
              />

              <DateInput
                clearable
                valueFormat="DD MMM YYYY"
                minDate={new Date()}
                mt="md"
                label="Fecha de vencimiento"
                placeholder="Elija la fecha de vencimiento de la tarea"
                value={data.due_on}
                onChange={(value) => updateValue("due_on", value)}
                readOnly={!can("editar tarea")}
              />

              <Select
                label='Tipo de check'
                placeholder='Seleccione el tipo del checklist'
                mt='md'
                required
                value={data.type_check}
                onChange={value => updateValue('type_check', value)}
                data={typeChecks}
              />

              <LabelsDropdown
                items={labels}
                selected={data.labels}
                onChange={(values) => updateValue("labels", values)}
                mt="md"
              />

              <NumberInput
                label="Estimación de tiempo"
                mt="md"
                decimalScale={2}
                fixedDecimalScale
                value={data.estimation}
                min={0}
                allowNegative={false}
                step={0.5}
                suffix=" horas"
                onChange={(value) => updateValue("estimation", value)}
                readOnly={!can("editar tarea")}
              />

              {/* {(can("ver registros de tiempo") || can("agregar registro de tiempo")) && <Timer mt="xl" task={task} />} */}

              <Checkbox
                label="Subir imagen"
                description="Habilitar para que sea obligatorio"
                mt="md"
                checked={data.sent_archive}
                onChange={(event) =>
                  updateValue("sent_archive", event.currentTarget.checked)
                }
                disabled={!can("editar tarea")}
              />

                <Checkbox
                  label="billable"
                  mt="xl"
                  display="none"
                  checked={data.billable}
                  onChange={(event) => updateValue("billable", event.currentTarget.checked)}
                />

              {!hasRoles(user, ["cliente"]) && (
                <Checkbox
                  label="Hidden from clients"
                  mt="md"
                  display="none"
                  checked={data.hidden_from_clients}
                  onChange={(event) =>
                    updateValue("hidden_from_clients", event.currentTarget.checked)
                  }
                  disabled={!can("editar tarea")}
                />
              )}

              <MultiSelect
                label="Suscriptores"
                placeholder={!data.subscribed_users.length ? "Seleccioanar suscriptores" : null}
                mt="lg"
                value={data.subscribed_users}
                onChange={(values) => updateValue("subscribed_users", values)}
                data={usersWithAccessToProject.map((i) => ({
                  value: i.id.toString(),
                  label: i.name,
                }))}
                readOnly={!can("editar tarea")}
              />
            </div>
          </form>
        </>
      ) : (
        <></>
      )}
    </Drawer>
  );
}
