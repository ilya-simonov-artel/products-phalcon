<template>
  <section class="panel auth-panel">
    <h2>Авторизация</h2>
    <form class="stack" @submit.prevent="submitLogin">
      <label>
        Логин
        <input v-model="store.credentials.username" type="text" placeholder="Введите логин" required :disabled="store.loading.login" />
        <small v-if="store.formErrors.login.username" class="field-error">{{ store.formErrors.login.username }}</small>
      </label>
      <label>
        Пароль
        <input v-model="store.credentials.password" type="password" placeholder="Введите пароль" required :disabled="store.loading.login" />
        <small v-if="store.formErrors.login.password" class="field-error">{{ store.formErrors.login.password }}</small>
      </label>
      <small v-if="store.formErrors.login._common" class="field-error">{{ store.formErrors.login._common }}</small>
      <button type="submit" :disabled="store.loading.login">{{ store.loading.login ? 'Вход...' : 'Войти' }}</button>
      <small>Тестовый пользователь: <code>demo</code> / <code>test12345</code>.</small>
    </form>
  </section>
</template>

<script lang="ts" setup>
import { useAppStore } from '../stores/appStore';
import { useNavigation } from '../stores/navigation';

const store = useAppStore();
const nav = useNavigation();

async function submitLogin() {
  if (store.loading.login) return;
  try {
    await store.login();
    nav.goTo('/products');
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Не удалось авторизоваться.', 'error');
  }
}
</script>
