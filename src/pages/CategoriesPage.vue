<template>
  <CategoryTree
    :categories="store.categoriesTree.value"
    @edit="goEdit"
    @remove="onRemove"
    @reset="goCreate"
  />
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import CategoryTree from '../components/CategoryTree.vue';
import { CategoryFlat, CategoryNode, useAppStore } from '../stores/appStore';
import { useNavigation } from '../stores/navigation';

const store = useAppStore();
const nav = useNavigation();

function goCreate() {
  store.resetCategoryForm();
  nav.goTo('/categories/new');
}

function goEdit(category: CategoryFlat | CategoryNode) {
  store.editCategory(category);
  nav.goTo(`/categories/${category.id}/edit`);
}

async function onRemove(id: number) {
  if (!confirm('Удалить категорию?')) return;

  try {
    await store.removeCategory(id);
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Не удалось удалить категорию.', 'error');
  }
}

onMounted(async () => {
  if (store.categoriesTree.value.length === 0) {
    await store.fetchCategories();
  }
});
</script>
