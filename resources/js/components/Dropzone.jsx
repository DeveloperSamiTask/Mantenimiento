import { isImage, isViewable } from "@/utils/file";
import { Group, SimpleGrid, Text, rem } from "@mantine/core";
import { Dropzone as MantineDropzone } from "@mantine/dropzone";
import { useDisclosure } from "@mantine/hooks";
import { IconFiles, IconUpload, IconX } from "@tabler/icons-react";
import JsFileDownloader from "js-file-downloader";
import { useRef, useState } from "react";
import { openConfirmModal } from "./ConfirmModal";
import { openConfirmModal as  openConfirmModalAttachment} from "./ConfirmAttachment";
import FileThumbnail from "./FileThumbnail";
import ImageModal from "./ImageModal";

export default function Dropzone({ selected, onChange, remove, ...props }) {

  const [opened, { close, open }] = useDisclosure(false);
  const [selectedImage, setSelectedImage] = useState(null);

  const confirmDeleteAttachment = (index) => {
    openConfirmModal({
      type: "danger",
      title: "Eliminar imagen",
      content: `¿Está seguro de que desea eliminar este archivo adjunto?`,
      confirmLabel: "Eliminar",
      confirmProps: { color: "red" },
      onConfirm: () => remove(index),
    });
  };

  const confirmViewAttachment = (image) => {
    openConfirmModalAttachment({
      title: image?.name,
      content: image,
    });
  };

  const openFile = (file) => {
    if (isImage(file)) {
      setSelectedImage(file);
      confirmViewAttachment(file);
      // open();
    } else if (isViewable(file)) {
      window.open(file.path, "_blank");
    } else {
      new JsFileDownloader({
        url: file.path,
        filename: file.name,
        contentType: file.type,
        nativeFallbackOnError: true,
      }).catch((error) => console.error("No se pudo descargar el archivo", error));
    }
  };

  return (
    <>
      {/* <ImageModal image={selectedImage} opened={opened} close={close} /> */}

      <MantineDropzone
        onDrop={(files) => onChange([...selected, ...files])}
        onReject={(files) => console.log("rejected files", files)}
        {...props}
      >
        <Group justify="center" gap="md" mih={50} style={{ pointerEvents: "none" }}>
          <MantineDropzone.Accept>
            <IconUpload
              style={{
                width: rem(42),
                height: rem(42),
                color: "var(--mantine-color-blue-6)",
              }}
              stroke={1.5}
            />
          </MantineDropzone.Accept>
          <MantineDropzone.Reject>
            <IconX
              style={{
                width: rem(42),
                height: rem(42),
                color: "var(--mantine-color-red-6)",
              }}
              stroke={1.5}
            />
          </MantineDropzone.Reject>
          <MantineDropzone.Idle>
            <IconFiles
              style={{
                width: rem(42),
                height: rem(42),
                color: "var(--mantine-color-dimmed)",
              }}
              stroke={1.5}
            />
          </MantineDropzone.Idle>

          <div>
            <Text size="md" inline>
              Arrastre los archivos aquí o haga clic para seleccionar
            </Text>
            <Text size="xs" c="dimmed" inline mt={7}>
              Se aceptarán archivos de cualquier tipo.
            </Text>
          </div>
        </Group>
      </MantineDropzone>

      <SimpleGrid cols={2} mt="lg">
        {selected.map((file, index) => (
          <FileThumbnail
            key={index}
            index={index}
            file={file}
            remove={() => confirmDeleteAttachment(index)}
            open={() => openFile(file)}
          />
        ))}
      </SimpleGrid>
    </>
  );
}
