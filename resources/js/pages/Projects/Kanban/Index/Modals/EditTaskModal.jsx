import useTaskDrawerStore from '@/hooks/store/useTaskDrawerStore';
import RichTextEditor from '@/components/RichTextEditor';
import useTasksStore from '@/hooks/store/useTasksStore';
import { Box, Button, Flex, Loader, LoadingOverlay, Text, TextInput } from '@mantine/core';
import { modals } from '@mantine/modals';
import { useEffect, useRef, useState } from 'react';
import Dropzone from '@/components/Dropzone';
import Comments from '../../../Tasks/Drawers/Comments';
import useProjectsStore from '@/hooks/store/useProjectsStore';

function ModalForm({task}) {

  const { findTask, updateTaskProperty, deleteAttachment, uploadAttachments } = useTasksStore();
  const { findProject, updateProjectProperty } = useProjectsStore();
  const editorRef = useRef(null);
  const project = findProject(task.project_id);
  const [loading, setLoading] = useState(false);

  const projectLocalStorage = task ? localStorage.getItem(`project-${project.id}`) : false;
  const commentLocalStorage = task ? localStorage.getItem(`project-comments-${project.id}`) : false;

  const [data, setData] = useState({
    name: '',
    description: '',
  });

  useEffect(() => {
    setData({
      name: task?.name || '',
    });
    editorRef.current?.setContent(task?.description || '');
  }, [task]);

  const updateProjectTasks = (project, taskId, field, value) => {
    const updatedTasks = project.tasks.map((updateTask) =>
      updateTask.id == taskId ? { ...updateTask, [field]: value } : updateTask
    );
    updateProjectProperty(project, 'tasks', updatedTasks);
  };

  const updateValue = (field, value) => {
    setData({ ...data, [field]: value });
    const onBlurInputs = ["name", "description"];
    if (!onBlurInputs.includes(field)) {
      updateTaskProperty(task, field, value);
    }
  };

  const onBlurUpdate = (property) => {
    if (data.name.length > 0) {
      updateProjectTasks(project, task.id, property, data[property])
      updateTaskProperty(task, property, data[property]);
    }
  };

  return (
    <form>

      <LoadingOverlay visible={loading} loaderProps={{ children: <Loader size={40} /> }} />

      <TextInput
        label='Nombre'
        placeholder='Nombre de la tarea'
        value={data.name}
        onChange={e => updateValue('name', e.target.value)}
        onBlur={() => onBlurUpdate('name')}
        error={data.name.length == 0}
        readOnly={!can('editar tarea') || projectLocalStorage || commentLocalStorage }
      />

      <RichTextEditor
        ref={editorRef}
        mt='xl'
        placeholder='Descripción de la tarea'
        content={data.description}
        height={260}
        onChange={content => updateValue('description', content)}
        onBlur={() => onBlurUpdate('description')}
        readOnly={!can('editar tarea') || projectLocalStorage || commentLocalStorage }
      />

      {can('completar tarea') && (
        <Dropzone
          mt='xl'
          selected={projectLocalStorage && findTask(task.id).attachments.length == 0 ? task.attachments : findTask(task.id).attachments}
          onChange={files =>  {
            uploadAttachments(task, files, setLoading);
            updateProjectTasks(project, task.id, 'attachments', findTask(task.id).attachments);
          }}
          remove={index => {
            deleteAttachment(task, index, setLoading);
            updateProjectTasks(project, task.id, 'attachments', findTask(task.id).attachments);

          }}
        />
      )}

      {can('ver comentarios') && <Comments task={task} />}
    </form>
  );
}

const EditTaskModal = (task) => {
  modals.open({
    title: (
      <Text
        size='xl'
        fw={700}
        mb={-10}
      >
        Editar tarea
      </Text>
    ),
    centered: true,
    size: '70%',
    padding: 'xl',
    overlayProps: {
      backgroundOpacity: 0.55,
      blur: 3,
    },
    children: <ModalForm task={task} />,
  });
};

export default EditTaskModal;
