<template>
  <div>
    <h3>
      {{ $t('settings.users.new') }}
    </h3>
    <hr>

    <b-overlay :show="isBusy">
      <template #overlay>
        <div class="text-center">
          <b-spinner></b-spinner>
        </div>
      </template>

      <b-container :fluid='true'>
        <b-form @submit="save">
          <b-form-group
            label-cols-lg="12"
            :label="$t('settings.users.base_data')"
            label-size="lg"
            label-class="font-weight-bold pt-0"
            class="mb-0"
          >
            <b-form-group
              label-cols-sm='3'
              :label="$t('app.firstname')"
              label-for='firstname'
              :state='fieldState("firstname")'
            >
              <b-form-input
                id='firstname'
                required
                type='text'
                v-model='model.firstname'
                :state='fieldState("firstname")'
                :disabled="isBusy"
              ></b-form-input>
              <template #invalid-feedback><div v-html="fieldError('firstname')"></div></template>
            </b-form-group>
            <b-form-group
              label-cols-sm='3'
              :label="$t('app.lastname')"
              label-for='lastname'
              :state='fieldState("lastname")'
            >
              <b-form-input
                id='lastname'
                type='text'
                required
                v-model='model.lastname'
                :state='fieldState("lastname")'
                :disabled="isBusy"
              ></b-form-input>
              <template #invalid-feedback><div v-html="fieldError('lastname')"></div></template>
            </b-form-group>
            <b-form-group
              label-cols-sm='3'
              :label="$t('app.email')"
              label-for='email'
              :state='fieldState("email")'
            >
              <b-form-input
                id='email'
                type='email'
                required
                v-model='model.email'
                :state='fieldState("email")'
                :disabled="isBusy"
              ></b-form-input>
              <template #invalid-feedback><div v-html="fieldError('email')"></div></template>
            </b-form-group>
            <b-form-group
              label-cols-sm='3'
              :label="$t('settings.users.user_locale')"
              label-for='user_locale'
              :state='fieldState("user_locale")'
            >
              <locale-select
                id='user_locale'
                required
                v-model="model.user_locale"
                :state='fieldState("user_locale")'
                :disabled="isBusy"
              ></locale-select>
              <template #invalid-feedback><div v-html="fieldError('user_locale')"></div></template>
            </b-form-group>
            <b-form-group
              label-cols-sm='3'
              :label="$t('settings.users.timezone')"
              label-for='timezone'
              :state='fieldState("timezone")'
            >
              <timezone-select
                id='timezone'
                required
                v-model="model.timezone"
                :state='fieldState("timezone")'
                :disabled="isBusy"
                @loadingError="(value) => this.timezonesLoadingError = value"
                @busy="(value) => this.timezonesLoading = value"
                :placeholder="$t('settings.users.timezone')"
              >
              </timezone-select>
              <template #invalid-feedback><div v-html="fieldError('timezone')"></div></template>
            </b-form-group>
            <b-form-group
              label-cols-sm='3'
              :label="$t('app.roles')"
              label-for='roles'
              :state='fieldState("roles", true)'
            >
              <role-select
                id='roles'
                v-model='model.roles'
                :invalid='fieldState("roles", true)===false'
                :disabled="isBusy"
                @loadingError="(value) => this.rolesLoadingError = value"
                @busy="(value) => this.rolesLoading = value"
              ></role-select>
              <template #invalid-feedback><div v-html="fieldError('roles', true)"></div></template>
            </b-form-group>
          </b-form-group>
          <hr>
          <b-form-group
            label-cols-lg="12"
            :label="$t('auth.password')"
            label-size="lg"
            label-class="font-weight-bold pt-0"
            class="mb-0"
          >
            <b-form-group
              label-cols-sm='3'
              :label="$t('settings.users.generate_password')"
              label-for='generate_password'
              :state="fieldState('generate_password')"
              :description="$t('settings.users.generate_password_description')"
              class="align-items-center d-flex"
            >
              <b-form-checkbox
                id='generate_password'
                v-model='generate_password'
                :state="fieldState('generate_password')"
                :disabled="isBusy"
                switch
              ></b-form-checkbox>
              <template #invalid-feedback><div v-html="fieldError('generate_password')"></div></template>
            </b-form-group>
            <b-form-group
              v-if="!generate_password"
              label-cols-sm='3'
              :label="$t('auth.new_password')"
              label-for='new_password'
              :state='fieldState("new_password")'
            >
              <b-input-group>
                <b-form-input
                  id='new_password'
                  :type='showPassword ? "text" : "password"'
                  required
                  v-model='model.new_password'
                  :state='fieldState("new_password")'
                  :disabled="isBusy"
                ></b-form-input>
                <template #append>
                  <b-button
                    @click="showPassword = !showPassword"
                    :disabled='isBusy'
                    v-tooltip-hide-click
                    v-b-tooltip.hover
                    :title="!showPassword ? $t('settings.users.show_password') : $t('settings.users.hide_password')"
                    variant="secondary"
                  >
                    <i class="fa-solid fa-eye" v-if="!showPassword"></i><i class="fa-solid fa-eye-slash" v-else></i>
                  </b-button>
                </template>
              </b-input-group>

              <template #invalid-feedback><div v-html="fieldError('new_password')"></div></template>
            </b-form-group>
            <b-form-group
              v-if="!generate_password"
              label-cols-sm='3'
              :label="$t('auth.new_password_confirmation')"
              label-for='new_password_confirmation'
              :state='fieldState("password_confirmation")'
            >
              <b-form-input
                id='new_password_confirmation'
                :type='showPassword ? "text" : "password"'
                required
                v-model='model.new_password_confirmation'
                :state='fieldState("new_password_confirmation")'
                :disabled="isBusy"
              ></b-form-input>
              <template #invalid-feedback><div v-html="fieldError('new_password_confirmation')"></div></template>
            </b-form-group>
          </b-form-group>
          <b-button
            :disabled='isBusy || rolesLoadingError || timezonesLoadingError || rolesLoading || timezonesLoading'
            variant='success'
            type='submit'
            >
            <i class='fa-solid fa-save'></i> {{ $t('app.save') }}
          </b-button>
        </b-form>
      </b-container>
      </b-overlay>
  </div>
</template>

<script>
import FieldErrors from '../../../mixins/FieldErrors';
import Base from '../../../api/base';
import env from '../../../env';
import RoleSelect from '../../../components/Inputs/RoleSelect.vue';
import 'cropperjs/dist/cropper.css';
import LocaleSelect from '../../../components/Inputs/LocaleSelect.vue';
import TimezoneSelect from '../../../components/Inputs/TimezoneSelect.vue';
import { mapState } from 'pinia';
import { useSettingsStore } from '../../../stores/settings';

export default {
  mixins: [FieldErrors],
  components: { TimezoneSelect, LocaleSelect, RoleSelect },
  data () {
    return {
      isBusy: false,
      showPassword: false,
      model: {
        firstname: null,
        lastname: null,
        email: null,
        new_password: null,
        new_password_confirmation: null,
        user_locale: null,
        timezone: null,
        roles: []
      },
      generate_password: false,
      errors: {},
      rolesLoading: false,
      rolesLoadingError: false,
      timezonesLoading: false,
      timezonesLoadingError: false
    };
  },

  /**
   * Loads the user, part of roles that can be selected and enables an event listener
   * to enable or disable the edition of roles and attributes when the permissions
   * of the current user gets changed.
   */
  mounted () {
    this.model.user_locale = this.getSetting('default_locale');
    this.model.timezone = this.getSetting('default_timezone');
  },

  computed: {
    ...mapState(useSettingsStore, ['getSetting'])
  },

  methods: {
    /**
     * Create new user by making a POST request to the API.
     *
     */
    save (evt) {
      if (evt) {
        evt.preventDefault();
      }

      this.isBusy = true;
      this.errors = {};

      const data = {
        firstname: this.model.firstname,
        lastname: this.model.lastname,
        username: this.model.username,
        email: this.model.email,
        user_locale: this.model.user_locale,
        timezone: this.model.timezone,
        roles: this.model.roles.map(role => role.id),
        generate_password: this.generate_password
      };

      if (!this.generate_password) {
        data.new_password = this.model.new_password;
        data.new_password_confirmation = this.model.new_password_confirmation;
      }

      Base.call('users', {
        method: 'POST',
        data
      }).then(response => {
        this.$router.push({ name: 'settings.users.view', params: { id: response.data.data.id } });
      }).catch(error => {
        if (error.response && error.response.status === env.HTTP_UNPROCESSABLE_ENTITY) {
          this.errors = error.response.data.errors;
        } else {
          Base.error(error, this.$root, error.message);
        }
      }).finally(() => {
        this.isBusy = false;
      });
    }
  }
};

</script>
