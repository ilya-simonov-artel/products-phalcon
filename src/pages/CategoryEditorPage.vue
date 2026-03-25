<template>
  <section class="panel single-column">
    <div class="panel-header">
      <h2>{{ store.categoryForm.id ? 'Редактирование категории' : 'Новая категория' }}</h2>
      <button @click="store.resetCategoryForm">Сбросить</button>
    </div>
    <CategoryForm
      :category="store.categoryForm"
      :categories="store.categoriesFlat.value"
      :submitting="store.loading.categorySubmit"
      :errors="store.formErrors.category"
      @submit="save"
      @reset="store.resetCategoryForm"
    />
  </section>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import CategoryForm from '../components/CategoryForm.vue';
import { useAppStore } from '../stores/appStore';
import { useNavigation } from '../stores/navigation';

const store = useAppStore();
const nav = useNavigation();

async function save() {
  if (store.loading.categorySubmit) return;
  try {
    await store.submitCategory();
    nav.goTo('/categories');
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Не удалось сохранить категорию.', 'error');
  }
}

onMounted(async () => {
  if (store.categoriesFlat.value.length === 0) {
    await store.fetchCategories();
  }

  if (nav.routeName.value === 'category-create') {
    store.resetCategoryForm();
  }
});
</script>
