<template>
  <div>
    <div
      ref="roomDescription"
      class="room-description link-color px-2"
      data-test="room-description-viewer"
      v-html="props.html"
    />
    <ConfirmDialog
      data-test="room-description-viewer-confirm-dialog"
      :pt="{
        pcRejectButton: {
          root: {
            'data-test': 'confirm-dialog-reject-button',
          },
        },
        pcAcceptButton: {
          root: {
            'data-test': 'confirm-dialog-accept-button',
          },
        },
      }"
    />
  </div>
</template>
<script setup>
import { useConfirm } from "primevue/useconfirm";
import { useI18n } from "vue-i18n";
import { onMounted, onUpdated, ref } from "vue";
import ConfirmDialog from "primevue/confirmdialog";

const props = defineProps({
  html: {
    type: String,
    required: true,
  },
});

const confirm = useConfirm();
const { t } = useI18n();
const roomDescription = ref(null);

function confirmOpenLink(link) {
  confirm.require({
    message: t("rooms.description.external_link_warning.description", { link }),
    header: t("rooms.description.external_link_warning.title"),
    icon: "fa-solid fa-triangle-exclamation",
    rejectProps: {
      label: t("app.cancel"),
      severity: "secondary",
    },
    acceptProps: {
      label: t("app.continue"),
    },
    accept: () => {
      window.open(link, "_blank");
    },
  });
}

onUpdated(() => {
  // Call addSafeLinkListeners on update to make sure all links
  // are covered even after the description was updated
  addSafeLinkListeners();
});

onMounted(() => {
  // Call addSafeLinkListeners on mount to make sure all links
  // are covered on first render
  addSafeLinkListeners();
});

/**
 * Add listeners to all links in the room description
 */
function addSafeLinkListeners() {
  const safeLinks = roomDescription.value.querySelectorAll("[href]");
  safeLinks.forEach((link) => {
    link.classList.add("link");
    link.addEventListener("click", (event) => {
      event.preventDefault();
      confirmOpenLink(link.getAttribute("href"));
    });
  });
}
</script>
