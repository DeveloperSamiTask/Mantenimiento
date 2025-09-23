import ArchivedFilterButton from "@/components/ArchivedFilterButton";
import EmptyWithIcon from "@/components/EmptyWithIcon";
import SearchInput from "@/components/SearchInput";
import useAuthorization from "@/hooks/useAuthorization";
import Layout from "@/layouts/MainLayout";
import { redirectTo, reloadWithQuery } from "@/utils/route";
import { usePage } from "@inertiajs/react";
import { Button, Center, Flex, Grid, Group } from "@mantine/core";
import { IconPlus, IconSearch } from "@tabler/icons-react";
import ProjectCard from "./Index/ProjectCard";
import Pagination from "@/components/Pagination";

/*Por defecto solo debe mostrarme 20 antes de hacer la busqueda
  en el seacrh dos letras o mas en el search de abajo (orden de trabjao)
  consola -> red , puedo ver que me trae

  */
/*Esta es la vista para plan de tareas */
const ProjectsIndex = () => {
  const { items } = usePage().props;
  const { isAdmin } = useAuthorization();

  const search = (search) => reloadWithQuery({ search });

  return (
    <>
      <Grid justify="space-between" align="center">
        <Grid.Col span="content">
          <Group>
            <SearchInput placeholder="Buscar ordenes de trabajo" search={search} />
            {isAdmin() && <ArchivedFilterButton />}
          </Group>
        </Grid.Col>
        <Grid.Col span="content">
          {can("crear proyecto") && (
            <Button
              leftSection={<IconPlus size={14} />}
              radius="xl"
              onClick={() => redirectTo("projects.create")}
            >
              Crear
            </Button>
          )}
        </Grid.Col>
      </Grid>

      {items.length ? (
        <Flex mt="xl" gap="lg" justify="flex-start" align="flex-start" direction="row" wrap="wrap">
          {items.map((item) => (
            <ProjectCard item={item} key={item.id} />
          ))}
        </Flex>
      ) : (
        <Center mih={400}>
          <EmptyWithIcon
            title="No se encontraron ordenes de trabajo"
            subtitle="O no tienes acceso a ninguno de ellos."
            icon={IconSearch}
          />
        </Center>
      )}
    </>
  );
};

ProjectsIndex.layout = (page) => <Layout title="Ordenees de trabajo">{page}</Layout>;

export default ProjectsIndex;
