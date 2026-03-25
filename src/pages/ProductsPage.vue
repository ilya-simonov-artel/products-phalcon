<template>
  <div class="grid">
    <ProductFiltersSummary
      :categories="store.categoriesFlat.value"
      :filters="store.filters"
      :pagination="store.pagination"
      :aggregate="store.aggregate"
      @reset="onResetFilters"
      @update:category="onCategoryChange"
      @update:in-stock="onInStockChange"
      @update:limit="onLimitChange"
    />

    <ProductList
      :products="store.products.value"
      :pagination="store.pagination"
      :filters="store.filters"
      @create="goCreate"
      @edit="goEdit"
      @remove="onRemove"
      @change-page="onPage"
    />
  </div>
</template>

<script lang="ts" setup>
import { onMounted, watch } from 'vue';
import ProductFiltersSummary from '../components/ProductFiltersSummary.vue';
import ProductList from '../components/ProductList.vue';
import { Product, useAppStore } from '../stores/appStore';
import { useNavigation } from '../stores/navigation';

const store = useAppStore();
const nav = useNavigation();

async function refresh() {
  await store.refreshAll();
}

async function onResetFilters() {
  const hasChanges = store.filters.category_id !== '' || store.filters.in_stock !== '' || store.pagination.limit !== 10;

  store.filters.category_id = '';
  store.filters.in_stock = '';
  store.pagination.limit = 10;
  store.pagination.page = 1;

  if (hasChanges) return;

  try {
    await store.applyProductFilters();
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Ошибка сброса фильтров.', 'error');
  }
}

function onCategoryChange(value: string) {
  store.filters.category_id = value;
}

function onInStockChange(value: string) {
  store.filters.in_stock = value;
}

function onLimitChange(value: number) {
  store.pagination.limit = value;
}

function goCreate() {
  store.resetProductForm();
  nav.goTo('/products/new');
}

function goEdit(product: Product) {
  store.editProduct(product);
  nav.goTo(`/products/${product.id}/edit`);
}

async function onRemove(id: number) {
  if (!confirm('Удалить товар?')) return;

  try {
    await store.removeProduct(id);
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Не удалось удалить товар.', 'error');
  }
}

async function onPage(page: number) {
  try {
    await store.changePage(page);
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Ошибка пагинации.', 'error');
  }
}

watch(() => [store.filters.category_id, store.filters.in_stock, store.pagination.limit], async () => {
  try {
    await store.applyProductFilters();
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Ошибка фильтрации.', 'error');
  }
});

onMounted(async () => {
  if (store.categoriesFlat.value.length === 0 || store.products.value.length === 0) {
    await refresh();
  }
});
</script>
