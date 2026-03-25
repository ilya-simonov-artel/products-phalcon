<template>
  <div class="app-shell">
    <aside v-if="store.isAuthenticated.value" class="app-shell__sidebar sidebar panel">
      <p class="eyebrow">Phalcon + MySQL + Vue 3</p>
      <h2>{{ appName }}</h2>
      <nav class="nav-links">
        <a
          :class="['nav-links__link', { 'nav-links__link--active': nav.isActive('/products') }]"
          href="#/products"
        >
          Товары и фильтры
        </a>
        <a
          :class="['nav-links__link', { 'nav-links__link--active': nav.isActive('/categories') }]"
          href="#/categories"
        >
          Список категорий
        </a>
      </nav>
      <div class="profile-box">
        <strong class="profile-box__name">{{ store.authUser.value?.display_name }}</strong>
        <small class="profile-box__username">{{ store.authUser.value?.username }}</small>
        <button @click="onLogout">Выйти</button>
      </div>
    </aside>

    <main class="app-shell__content content-area layout">
      <header class="hero">
        <div>
          <p class="eyebrow">Phalcon + MySQL + Vue 3</p>
          <h1>{{ appName }}</h1>
          <p class="hero__description">Страницы авторизации, товаров, категорий и редактирования разделены маршрутизацией.</p>
        </div>
      </header>

      <Message :message="store.message" />
      <component :is="activePage" />
    </main>
  </div>
</template>

<script lang="ts" setup>
import { computed, onMounted } from 'vue';
import Message from './components/Message.vue';
import CategoriesPage from './pages/CategoriesPage.vue';
import CategoryEditorPage from './pages/CategoryEditorPage.vue';
import LoginPage from './pages/LoginPage.vue';
import ProductEditorPage from './pages/ProductEditorPage.vue';
import ProductsPage from './pages/ProductsPage.vue';
import { useNavigation } from './stores/navigation';
import { useAppStore } from './stores/appStore';

const appName = (window as any).APP_NAME || 'Product Catalog';
const store = useAppStore();
const nav = useNavigation();

const activePage = computed(() => {
  switch (nav.routeName.value) {
    case 'login':
      return LoginPage;
    case 'products':
      return ProductsPage;
    case 'product-create':
    case 'product-edit':
      return ProductEditorPage;
    case 'categories':
      return CategoriesPage;
    case 'category-create':
    case 'category-edit':
      return CategoryEditorPage;
    default:
      return ProductsPage;
  }
});

async function onLogout() {
  await store.logout();
  nav.goTo('/login');
}

onMounted(async () => {
  await store.restoreSession();
  nav.ensureCurrent();
});
</script>
