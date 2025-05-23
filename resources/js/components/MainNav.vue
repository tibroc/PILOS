<template>
  <div class="border-b bg-white py-2 border-surface dark:bg-surface-900">
    <div class="container flex flex-row justify-between">
      <Menubar
        :breakpoint="menuBreakpoint + 'px'"
        :model="mainMenuItems"
        :pt="{
          root: 'm-0 border-0',
          menu: {
            class: 'gap-1 px-2',
          },
          action: {
            class: 'p-2',
          },
        }"
      >
        <template #start>
          <RouterLink
            v-if="settingsStore.getSetting('theme.logo')"
            :to="{ name: 'home' }"
            class="mr-12"
            data-test="navbar-home"
          >
            <img
              style="height: 2rem"
              :src="
                isDark
                  ? settingsStore.getSetting('theme.logo_dark')
                  : settingsStore.getSetting('theme.logo')
              "
              alt="Logo"
            />
          </RouterLink>
        </template>
        <template #item="{ item, props, hasSubmenu, root }">
          <router-link
            v-if="item.route"
            v-slot="{ href, navigate }"
            :to="item.route"
            custom
          >
            <a
              :href="href"
              v-bind="props.action"
              class="flex items-center"
              :data-test="item.dataTest"
              @click="navigate"
            >
              <span>{{ item.label }}</span>
            </a>
          </router-link>
          <a
            v-else
            :href="item.url"
            :target="item.target"
            v-bind="props.action"
            class="flex items-center"
            :data-test="item.dataTest"
          >
            <span>{{ item.label }}</span>
            <i
              v-if="hasSubmenu"
              :class="[
                'fa-solid fa-chevron-down text-xs',
                {
                  'fa-chevron-down ml-2': root,
                  'fa-chevron-right ml-auto': !root,
                },
              ]"
            ></i>
          </a>
        </template>
      </Menubar>
      <Menubar
        v-if="!isMobile"
        :model="userMenuItems"
        :pt="{
          root: 'main-menu-right shrink-0 m-0 border-0',
          menu: {
            class: 'gap-1 px-2',
          },
          item: {
            class: 'relative',
          },
          submenu: {
            class: 'right-0',
            'data-test': 'submenu',
          },
        }"
      >
        <template #item="{ item, props, hasSubmenu, root }">
          <router-link
            v-if="item.route"
            v-slot="{ href, navigate }"
            :to="item.route"
            custom
          >
            <a
              :href="href"
              v-bind="props.action"
              class="flex items-center"
              :data-test="item.dataTest"
              @click="navigate"
            >
              <span v-if="!item.icon">{{ item.label }}</span>
            </a>
          </router-link>
          <a
            v-else
            :href="item.url"
            :target="item.target"
            v-bind="props.action"
            class="flex items-center"
            :data-test="item.dataTest"
          >
            <i v-if="item.icon" :class="item.icon" />
            <UserAvatar
              v-if="item?.type === 'userAvatar'"
              :firstname="authStore.currentUser.firstname"
              :lastname="authStore.currentUser.lastname"
              :image="authStore.currentUser.image"
              class="bg-secondary"
            />
            <MainNavDarkModeToggle v-if="item?.type === 'darkMode'" />
            <span v-if="!item?.type && !item.icon">{{ item.label }}</span>
            <i
              v-if="hasSubmenu"
              :class="[
                'fa-solid fa-chevron-down text-xs',
                {
                  'fa-chevron-down ml-2': root,
                  'fa-chevron-right ml-auto': !root,
                },
              ]"
            ></i>
          </a>
        </template>
      </Menubar>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useSettingsStore } from "../stores/settings.js";
import { useAuthStore } from "../stores/auth.js";
import { useUserPermissions } from "../composables/useUserPermission.js";
import { useI18n } from "vue-i18n";
import { useBreakpoints, useDark, useToggle } from "@vueuse/core";
import { useRoute, useRouter } from "vue-router";
import { useLoadingStore } from "../stores/loading.js";
import UserAvatar from "./UserAvatar.vue";
import env from "../env.js";
import { useLocaleStore } from "../stores/locale.js";
import { useApi } from "../composables/useApi.js";
import { useToast } from "../composables/useToast.js";

const menuBreakpoint = 1023;

const breakpoints = useBreakpoints({
  desktop: menuBreakpoint,
});

const isMobile = breakpoints.smallerOrEqual("desktop");

const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const userPermissions = useUserPermissions();
const loadingStore = useLoadingStore();
const api = useApi();
const localeStore = useLocaleStore();
const router = useRouter();
const route = useRoute();
const { t, te } = useI18n();
const toast = useToast();

const isDark = useDark();
const toggleDark = useToggle(isDark);

const mainMenuItems = computed(() => {
  const items = [];

  if (authStore.isAuthenticated) {
    items.push({
      label: t("app.rooms"),
      route: { name: "rooms.index" },
      dataTest: "navbar-rooms",
    });

    if (userPermissions.can("viewAny", "MeetingPolicy")) {
      items.push({
        label: t("meetings.currently_running"),
        route: { name: "meetings.index" },
        dataTest: "navbar-meetings",
      });
    }

    if (userPermissions.can("view", "AdminPolicy")) {
      items.push({
        label: t("admin.title"),
        route: { name: "admin" },
        dataTest: "navbar-admin",
      });
    }

    if (userPermissions.can("monitor", "SystemPolicy")) {
      const menuItem = {
        label: t("system.monitor.title"),
        dataTest: "navbar-monitor",
        items: [
          {
            label: t("system.monitor.pulse"),
            url: "/pulse",
            target: "_blank",
            dataTest: "navbar-monitor-pulse",
          },
          {
            label: t("system.monitor.horizon"),
            url: "/horizon",
            target: "_blank",
            dataTest: "navbar-monitor-horizon",
          },
        ],
      };

      if (settingsStore.getSetting("monitor.telescope")) {
        menuItem.items.push({
          label: t("system.monitor.telescope"),
          url: "/telescope",
          target: "_blank",
          dataTest: "navbar-monitor-telescope",
        });
      }

      items.push(menuItem);
    }
  }

  if (isMobile.value) {
    userMenuItems.value.forEach((item) => {
      items.push(item);
    });
  }

  return items;
});

const userMenuItems = computed(() => {
  const items = [];

  if (authStore.isAuthenticated) {
    items.push({
      class: "user-avatar",
      type: "userAvatar",
      dataTest: "navbar-user",
      label:
        authStore.currentUser.firstname + " " + authStore.currentUser.lastname,
      items: [
        {
          label: t("app.profile"),
          route: { name: "profile" },
          dataTest: "navbar-user-profile",
        },
        {
          label: t("auth.logout"),
          command: logout,
          dataTest: "navbar-user-logout",
        },
      ],
    });
  } else {
    items.push({
      label: t("auth.login"),
      route: loginRoute,
      dataTest: "navbar-login",
    });
  }

  if (settingsStore.getSetting("general.help_url")) {
    items.push({
      icon: "fa-solid fa-circle-question text-xl",
      label: t("app.help"),
      target: "_blank",
      dataTest: "navbar-help",
      url: settingsStore.getSetting("general.help_url"),
    });
  }

  items.push({
    type: "darkMode",
    dataTest: "navbar-dark-mode",
    label: isDark.value
      ? t("app.dark_mode_disable")
      : t("app.dark_mode_enable"),
    command: () => changeDarkMode(),
  });

  // Only show the locale menu if more than one locale is enabled
  if (locales.value.length > 1) {
    const localeItem = {
      icon: "fa-solid fa-language text-xl",
      label: t("app.change_locale"),
      dataTest: "navbar-locale",
      items: [],
    };

    locales.value.forEach((locale) => {
      localeItem.items.push({
        label: locale.label,
        dataTest: "navbar-locale-" + locale.locale,
        command: () => changeLocale(locale.locale),
      });
    });

    items.push(localeItem);
  }

  return items;
});

// Add a redirect query parameter to the login route if the current route has the redirectBackAfterLogin meta set to true
// This ensures that the user is redirected to the page he is currently on after login
// By default the user is redirected to the home page after login (see comment in router.js)
const loginRoute = computed(() => {
  const loginRoute = { name: "login" };
  if (route.meta.redirectBackAfterLogin === true) {
    loginRoute.query = { redirect: route.path };
  }
  return loginRoute;
});

async function logout() {
  let response;
  try {
    loadingStore.setLoading();
    response = await authStore.logout();
  } catch (error) {
    loadingStore.setLoadingFinished();
    toast.error(t("auth.flash.logout_error"));
    console.error(error);
    return;
  }

  if (response.data.redirect) {
    window.location = response.data.redirect;
    return;
  }

  await router.push({ name: "logout" });

  loadingStore.setLoadingFinished();
}

function changeDarkMode() {
  // Check if the browser supports view transitions
  // If it doesn't, just toggle the dark mode
  if (!document.startViewTransition) {
    toggleDark();

    return;
  }

  // Wrap the dark mode toggle in a view transition
  // this will add a smooth transition
  document.startViewTransition(toggleDark);
}

const locales = computed(() => {
  const locales = settingsStore.getSetting("general.enabled_locales");
  if (!locales) {
    return [];
  }

  return Object.entries(locales).map(([locale, label]) => {
    let localeLabel = label;
    const localeTranslationKey = "app.locales." + locale;
    if (localeStore.currentLocale !== locale && te(localeTranslationKey)) {
      const translatedLabel = t(localeTranslationKey);
      localeLabel = localeLabel + " (" + translatedLabel + ")";
    }

    return {
      label: localeLabel,
      locale,
    };
  });
});

async function changeLocale(locale) {
  loadingStore.setOverlayLoading();
  try {
    await api.call("locale", {
      data: { locale },
      method: "post",
    });

    await localeStore.setLocale(locale);
  } catch (error) {
    if (
      error.response !== undefined &&
      error.response.status === env.HTTP_UNPROCESSABLE_ENTITY
    ) {
      toast.error(error.response.data.errors.locale.join(" "));
    } else {
      loadingStore.setOverlayLoadingFinished();
      api.error(error);
    }
  } finally {
    loadingStore.setOverlayLoadingFinished();
  }
}
</script>
