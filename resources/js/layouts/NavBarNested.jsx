import Logo from "@/components/Logo";
import useNavigationStore from "@/hooks/store/useNavigationStore";
import { usePage } from "@inertiajs/react";
import { Group, ScrollArea, Text, rem } from "@mantine/core";
import {
  IconBuildingMosque,
  IconBuildingSkyscraper,
  IconFileDollar,
  IconGauge,
  IconLayoutList,
  IconListDetails,
  IconReportAnalytics,
  IconSettings,
  IconUsers,
} from "@tabler/icons-react";
import { useEffect } from "react";
import NavbarLinksGroup from "./NavbarLinksGroup";
import UserButton from "./UserButton";
import classes from "./css/NavBarNested.module.css";

export default function Sidebar() {
  const { version } = usePage().props;
  const { items, setItems } = useNavigationStore();

  useEffect(() => {
    setItems([
      {
        label: "Dashboard",
        icon: IconGauge,
        link: route("dashboard"),
        active: route().current("dashboard"),
        visible: true,
      },
      {
        label: "Tareas",
        icon: IconListDetails,
        opened: route().current("projects.*"),
        active: route().current("projects.*"),
        visible: can("ver proyectos") || can("ver tareas"),
        links: [
          {
            label: "Plan de tareas",
            link: route("projects.index"),
            active: route().current("projects"),
            visible: can("ver proyectos"),
          },
          {
            label: "Ordenes de trabajo",
            link: route("projects.kanban"),
            active: route().current("projects.kanban"),
            visible: can("ver tareas"),
          },
        ]
      },
      // {
      //   label: "My Work",
      //   icon: IconLayoutList,
      //   active: route().current("my-work.*"),
      //   opened: route().current("my-work.*"),
      //   visible: can("ver tareas") || can("ver actividades"),
      //   links: [
      //     {
      //       label: "Tareas",
      //       link: route("my-work.tasks.index"),
      //       active: route().current("my-work.tasks.*"),
      //       visible: can("ver tasks"),
      //     },
      //     {
      //       label: "Actividades",
      //       link: route("my-work.activity.index"),
      //       active: route().current("my-work.activity.*"),
      //       visible: can("ver actividades"),
      //     },
      //   ],
      // },
      {
        label: "Atracciones",
        icon: IconBuildingMosque,
        active: route().current("attractions.*"),
        opened: route().current("attractions.*"),
        visible: can("ver juegos") || can('ver ubicaciones') || can('ver check lists'), // Modificar segun rol del usuario
        links: [
          {
            label: "Atracción",
            link: route("attractions.games.index"),
            active: route().current("attractions.games.*"),
            visible: can("ver juegos"), // Verificar roles
          },
          {
            label: "Ubicaciones",
            link: route("attractions.assets.index"),
            active: route().current("attractions.assets.*"),
            visible: can("ver ubicaciones"), // Verificar roles
          },
          {
            label: "Check-List",
            link: route("attractions.checklists.index"),
            active: route().current("attractions.checklists.*"),
            visible: can("ver checklists"), // Verificar roles
          },
        ],
      },
      {
        label: "Clientes",
        icon: IconBuildingSkyscraper,
        active: route().current("clients.*"),
        opened: route().current("clients.*"),
        visible: can("ver usuarios cliente") || can("ver empresas cliente"),
        links: [
          {
            label: "Usuarios",
            link: route("clients.users.index"),
            active: route().current("clients.users.*"),
            visible: can("ver usuarios cliente"),
          },
          {
            label: "Empresas",
            link: route("clients.companies.index"),
            active: route().current("clients.companies.*"),
            visible: can("ver empresas cliente"),
          },
        ],
      },
      {
        label: "Usuarios",
        icon: IconUsers,
        link: route("users.index"),
        active: route().current("users.*"),
        visible: can("ver usuarios"),
      },
      {
        label: "Facturas",
        icon: IconFileDollar,
        link: route("invoices.index"),
        active: route().current("invoices.*"),
        visible: can("ver facturas"),
      },
      {
        label: "Reportes",
        icon: IconReportAnalytics,
        active: route().current("reports.*"),
        opened: route().current("reports.*"),
        visible: can("ver informe de suma de tiempo registrado") || can("ver informe diario de tiempo registrado"),
        links: [
          {
            label: "Suma de tiempo registrado",
            link: route("reports.logged-time.sum"),
            active: route().current("reports.logged-time.sum"),
            visible: can("ver informe de suma de tiempo registrado"),
          },
          {
            label: "Tiempo registrado diariamente",
            link: route("reports.logged-time.daily"),
            active: route().current("reports.logged-time.daily"),
            visible: can("ver informe diario de tiempo registrado"),
          },
          {
            label: "Buscar Orden de trabajo",
            link: route("reports.search-projects"),
            active: route().current("reports.search-projects"),
            visible: can("ver proyecto"),
          },
        ],
      },
      {
        label: "Configuraciones",
        icon: IconSettings,
        active: route().current("settings.*"),
        opened: route().current("settings.*"),
        visible: can("ver mi empresa") || can("ver roles") || can("ver etiquetas"),
        links: [
          {
            label: "Mi empresa",
            link: route("settings.company.edit"),
            active: route().current("settings.company.*"),
            visible: can("ver mi empresa"),
          },
          {
            label: "Roles",
            link: route("settings.roles.index"),
            active: route().current("settings.roles.*"),
            visible: can("ver roles"),
          },
          {
            label: "Etiquetas",
            link: route("settings.labels.index"),
            active: route().current("settings.labels.*"),
            visible: can("ver etiquetas"),
          },
          {
            label: "Tipos de check",
            link: route("settings.typesCheck.index"),
            active: route().current("settings.typesCheck.*"),
            visible: can("editar proyecto"),
          },
        ],
      },
    ]);
  }, []);

  return (
    <nav className={classes.navbar}>
      <div className={classes.header}>
        <Group justify="space-between">
          {/* <Logo style={{ width: rem(120) }} /> */}
          <Text size="xl" className={classes.version}>
            Sistema Mantenimiento GV
          </Text>
        </Group>
      </div>

      <ScrollArea className={classes.links}>
        <div className={classes.linksInner}>
          {items
            .filter((i) => i.visible)
            .map((item) => (
              <NavbarLinksGroup key={item.label} item={item} />
            ))}
        </div>
      </ScrollArea>

      <div className={classes.footer}>
        <UserButton />
      </div>
    </nav>
  );
}
