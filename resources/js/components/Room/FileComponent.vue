<template>
  <div>
    <b-overlay :show="isBusy" >
      <b-alert show v-if="requireAgreement && files.files && files.files.length>0" >
        <strong>{{ $t('rooms.files.terms_of_use.title')}}</strong><br>
        {{ $t('rooms.files.terms_of_use.content')}}
        <hr>
        <b-form-checkbox
          v-model="downloadAgreement"
          :value="true"
          :unchecked-value="false"
        >
          {{ $t('rooms.files.terms_of_use.accept')}}
        </b-form-checkbox>
      </b-alert>

    <div class="row mb-3">
      <div class="col-10">

        <can method="manageSettings" :policy="room">

          <!-- Upload new file -->
          <b-form-file
            :disabled="isBusy"
            :state="fieldState('file')"
            :browse-text="$t('app.browse')"
            :placeholder="$t('rooms.files.select_or_drag')"
            @input="uploadFile"
            v-model="fileUpload"
            v-bind:multiple="false"
          >
          </b-form-file>
          <b-form-invalid-feedback :state="fieldState('file')" v-html="fieldError('file')"></b-form-invalid-feedback>

          <b-form-text>{{ $t('rooms.files.formats',{formats: getSetting('bbb.file_mimes')}) }}<br>{{ $t('rooms.files.size',{size: getSetting('bbb.max_filesize')}) }}</b-form-text>

        </can>
      </div>
      <div class="col-2">
        <!-- Reload file list -->
        <b-button
          v-if="!hideReload"
          class="float-right"
          variant="secondary"
          :disabled="isBusy"
          @click="reload"
          :title="$t('app.reload')"
          v-b-tooltip.hover
          v-tooltip-hide-click
        >
          <i class="fa-solid fa-sync"></i>
        </b-button>
      </div>
    </div>
      <!-- Display files -->
      <b-table
        :current-page="currentPage"
        :per-page="getSetting('pagination_page_size')"
        :fields="filefields"
        sort-by="uploaded"
        :sort-desc="true"
        v-if="files.files"
        :items="files.files"
        hover
        stacked="lg"
        show-empty
      >
        <!-- Show message on empty file list -->
        <template v-slot:empty>
          <i>{{ $t('rooms.files.nodata') }}</i>
        </template>

        <!-- Show spinner while table is loading -->
        <template v-slot:table-busy>
          <div class="text-center my-2">
            <b-spinner class="align-middle"></b-spinner>
          </div>
        </template>

        <!-- Render action column -->
        <template v-slot:cell(actions)="data">
          <b-button-group class="float-md-right">
            <can method="manageSettings" :policy="room">
              <!-- Delete file -->
              <b-button
                variant="danger"
                :disabled="loadingDownload===data.item.id"
                @click="showDeleteFileModal(data.item)"
                :title="$t('rooms.files.delete')"
                v-b-tooltip.hover
                v-tooltip-hide-click
              >
                <i class="fa-solid fa-trash"></i>
              </b-button>
            </can>
            <!-- View file -->
            <b-button
              variant="secondary"
              @click="downloadFile(data.item)"
              :disabled="disableDownload"
              target="_blank"
              :title="$t('rooms.files.view')"
              v-b-tooltip.hover
              v-tooltip-hide-click
            >
              <b-spinner small v-if="loadingDownload===data.item.id"></b-spinner> <i v-else class="fa-solid fa-eye"></i>
            </b-button>
          </b-button-group>
        </template>

        <!-- Checkbox if file should be downloadable by all room participants -->
        <template v-slot:cell(download)="data">
          <b-form-checkbox
            size="lg"
            switch
            @change="changeSettings(data.item,'download',$event)"
            v-model="data.item.download"
          ></b-form-checkbox>
        </template>

        <!--
        Checkbox if file should be send to the api on the next meeting start,
        setting can't be changed manually if the file is the default presentation
        -->
        <template v-slot:cell(use_in_meeting)="data">
          <b-form-checkbox
            size="lg"
            switch
            @change="changeSettings(data.item,'use_in_meeting',$event)"
            v-model="data.item.use_in_meeting"
          ></b-form-checkbox>
        </template>

        <!-- Checkbox if the file should be default/first in the next api call to start a meeting -->
        <template v-slot:cell(default)="data">
          <b-form-radio
            size="lg"
            name="default"
            :value="data.item.id"
            :disabled="data.item.use_in_meeting !== true"
            @change="changeSettings(data.item,'default',$event)"
            v-model="files.default"
          ></b-form-radio>
        </template>

        <!-- Checkbox if the file should be default/first in the next api call to start a meeting -->
        <template v-slot:cell(uploaded)="data">
         {{ $d(new Date(data.item.uploaded), 'datetimeLong') }}
        </template>

      </b-table>
      <b-row v-if="files.files">
        <b-col cols="12" class="my-1">
          <b-pagination
            v-if="files.files.length>getSetting('pagination_page_size')"
            v-model="currentPage"
            :total-rows="files.files.length"
            :per-page="getSetting('pagination_page_size')"
          ></b-pagination>
        </b-col>
      </b-row>
    </b-overlay>

    <!-- remove file modal -->
    <b-modal
      :busy="isLoadingAction"
      :static='modalStatic'
      ok-variant="danger"
      cancel-variant="secondary"
      :cancel-title="$t('app.no')"
      @ok="confirmDeleteFile"
      ref="delete-file-modal"
      :no-close-on-esc="isLoadingAction"
      :no-close-on-backdrop="isLoadingAction"
      :hide-header-close="isLoadingAction"
    >
      <template v-slot:modal-title>
        {{ $t('rooms.files.delete') }}
      </template>
      <template v-slot:modal-ok>
        <b-spinner small v-if="isLoadingAction"></b-spinner>  {{ $t('app.yes') }}
      </template>
      <span v-if="deleteFile">
        {{ $t('rooms.files.confirm_delete', { filename: deleteFile.filename }) }}
      </span>
    </b-modal>

  </div>
</template>
<script>
import Base from '../../api/base';
import Can from '../Permissions/Can.vue';
import PermissionService from '../../services/PermissionService';
import FieldErrors from '../../mixins/FieldErrors';
import env from './../../env.js';
import { mapState } from 'pinia';
import { useSettingsStore } from '../../stores/settings';

export default {

  components: {
    Can
  },
  mixins: [FieldErrors],
  props: {
    room: Object,

    accessCode: {
      type: String,
      required: false
    },
    token: {
      type: String,
      required: false
    },
    showTitle: {
      type: Boolean,
      default: false,
      required: false
    },
    hideReload: {
      type: Boolean,
      default: false,
      required: false
    },
    requireAgreement: {
      type: Boolean,
      default: false,
      required: false
    },
    emitErrors: {
      type: Boolean,
      default: false,
      required: false
    },
    modalStatic: {
      type: Boolean,
      default: false,
      required: false
    }
  },
  data () {
    return {
      // file list fetching from api
      isBusy: false,
      deleteFile: null, // file to be deleted
      isLoadingAction: false, // data is processed
      loadingDownload: null,
      // file upload model
      fileUpload: null,
      // file list from api
      files: [],
      errors: {},
      downloadAgreement: false,
      currentPage: 1
    };
  },
  methods: {
    /**
     * Remove given file from the list
     */
    removeFile: function (file) {
      this.files.files.splice(this.files.files.findIndex(item => item.id === file.id), 1);
    },

    /**
     * Request file download url
     * @param file file object
     * @return string url
     */
    downloadFile: function (file) {
      this.loadingDownload = file.id;
      // Update value for the setting and the effected file
      const config = {};

      if (this.token) {
        config.headers = { Token: this.token };
      } else if (this.accessCode != null) {
        config.headers = { 'Access-Code': this.accessCode };
      }

      const url = 'rooms/' + this.room.id + '/files/' + file.id;

      // Load data
      Base.call(url, config)
        .then(response => {
          if (response.data.url !== undefined) {
            window.open(response.data.url, '_blank');
          }
        }).catch((error) => {
          if (error.response) {
            // Access code invalid
            if (error.response.status === env.HTTP_UNAUTHORIZED && error.response.data.message === 'invalid_code') {
              return this.$emit('error', error);
            }

            // Room token is invalid
            if (error.response.status === env.HTTP_UNAUTHORIZED && error.response.data.message === 'invalid_token') {
              return this.$emit('error', error);
            }

            // Forbidden, require access code
            if (error.response.status === env.HTTP_FORBIDDEN && error.response.data.message === 'require_code') {
              return this.$emit('error', error);
            }

            // Forbidden, not allowed to download this file
            if (error.response.status === env.HTTP_FORBIDDEN) {
              // Show error message
              this.toastError(this.$t('rooms.flash.file_forbidden'));
              this.removeFile(file);
              return;
            }

            // File gone
            if (error.response.status === env.HTTP_NOT_FOUND) {
              // Show error message
              this.toastError(this.$t('rooms.flash.file_gone'));
              // Remove file from list
              this.removeFile(file);
              return;
            }
          }
          Base.error(error, this.$root);
        }).finally(() => {
          this.loadingDownload = null;
        });
    },

    /**
     * show modal to remove a file
     * @param file file to be deleted
     */
    showDeleteFileModal: function (file) {
      this.deleteFile = file;
      this.$refs['delete-file-modal'].show();
    },

    /**
     * Delete file
     */
    confirmDeleteFile: function (bvModalEvt) {
      // prevent modal from closing
      bvModalEvt.preventDefault();
      this.isLoadingAction = true;
      // Remove file from room with api call
      Base.call('rooms/' + this.room.id + '/files/' + this.deleteFile.id, {
        method: 'delete'
      }).then(response => {
        // Fetch successful
        this.files = response.data.data;
      }).catch((error) => {
        if (error.response.status === env.HTTP_NOT_FOUND) {
          // Show error message
          this.toastError(this.$t('rooms.flash.file_gone'));
          // Remove file from list
          this.removeFile(this.deleteFile);
          return;
        }
        Base.error(error, this.$root);
      }).finally(() => {
        this.$refs['delete-file-modal'].hide();
        this.isLoadingAction = false;
      });
    },
    /**
     * Handle file upload event on file select or drag'n'drop
     * @param event
     */
    uploadFile: function (file) {
      if (file == null) {
        return;
      }

      // Change table to busy state
      this.isBusy = true;
      // Reset errors
      this.errors = {};

      // Build form data
      const formData = new FormData();
      formData.append('file', file);

      // Send new file to api
      Base.call('rooms/' + this.room.id + '/files', {
        method: 'post',
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        data: formData
      }).then(response => {
        // Fetch successful
        this.files = response.data.data;
      }).catch((error) => {
        if (error.response) {
          if (error.response.status === env.HTTP_PAYLOAD_TOO_LARGE) {
            this.errors = { file: [this.$t('app.validation.too_large')] };
            return;
          }
          if (error.response.status === env.HTTP_UNPROCESSABLE_ENTITY) {
            this.errors = error.response.data.errors;
            return;
          }
        }
        Base.error(error, this.$root);
      }).finally(() => {
        // Clear file field and busy status
        this.isBusy = false;
        this.fileUpload = null;
      });
    },
    /**
     * Reload file list
     */
    reload: function () {
      // Change table to busy state
      this.isBusy = true;
      // Fetch file list
      const config = {};

      if (this.token) {
        config.headers = { Token: this.token };
      } else if (this.accessCode != null) {
        config.headers = { 'Access-Code': this.accessCode };
      }

      Base.call('rooms/' + this.room.id + '/files', config)
        .then(response => {
          // Fetch successful
          this.files = response.data.data;
        }).catch((error) => {
          if (this.emitErrors) { this.$emit('error', error); } else { Base.error(error, this.$root); }
        }).finally(() => {
          this.isBusy = false;
        });
    },
    /**
     * Change a setting for a file
     * @param file effected file
     * @param setting setting name
     * @param value new value
     */
    changeSettings: function (file, setting, value) {
      // Change table to busy state
      this.isBusy = true;

      if (setting === 'default') {
        value = true;
      }

      // Update value for the setting and the effected file
      Base.call('rooms/' + this.room.id + '/files/' + file.id, {
        method: 'put',
        data: { [setting]: value }
      }).then(response => {
        // Fetch successful
        this.files = response.data.data;
      }).catch((error) => {
        if (error.response.status === env.HTTP_NOT_FOUND) {
          // Show error message
          this.toastError(this.$t('rooms.flash.file_gone'));
          // Remove file from list
          this.removeFile(file);
          return;
        }
        Base.error(error, this.$root);
      }).finally(() => {
        this.isBusy = false;
      });
    }
  },
  computed: {

    ...mapState(useSettingsStore, ['getSetting']),

    // compute if the download buttons should be disabled
    disableDownload () {
      return this.loadingDownload !== null || (this.requireAgreement && !this.downloadAgreement);
    },

    // file table labels for columns
    filefields () {
      if (PermissionService.cannot('manageSettings', this.room)) {
        return [
          {
            key: 'filename',
            label: this.$t('rooms.files.filename'),
            sortable: true
          },
          {
            key: 'uploaded',
            label: this.$t('rooms.files.uploaded_at'),
            sortable: true
          },
          {
            key: 'actions',
            label: this.$t('app.actions')
          }
        ];
      }

      return [
        {
          key: 'filename',
          label: this.$t('rooms.files.filename'),
          sortable: true
        },
        {
          key: 'uploaded',
          label: this.$t('rooms.files.uploaded_at'),
          sortable: true
        },
        {
          key: 'download',
          label: this.$t('rooms.files.downloadable'),
          sortable: true
        },
        {
          key: 'use_in_meeting',
          label: this.$t('rooms.files.use_in_next_meeting'),
          sortable: true
        },
        {
          key: 'default',
          label: this.$t('rooms.files.default'),
          sortable: true
        },
        {
          key: 'actions',
          label: this.$t('app.actions')
        }
      ];
    }

  },
  created () {
    this.reload();
  }
};
</script>

<style>
  .custom-file-label {
    overflow: hidden;
  }
</style>
