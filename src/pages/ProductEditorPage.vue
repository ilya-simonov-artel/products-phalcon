<template>
  <div class="single-column">
    <ProductForm
      :product="store.productForm"
      :categories="store.categoriesFlat.value"
      :submitting="store.loading.productSubmit"
      :errors="store.formErrors.product"
      @submit="save"
      @reset="store.resetProductForm"
    />
  </div>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import ProductForm from '../components/ProductForm.vue';
import { useAppStore } from '../stores/appStore';
import { useNavigation } from '../stores/navigation';

const store = useAppStore();
const nav = useNavigation();

async function save() {
  if (store.loading.productSubmit) return;
  try {
    await store.submitProduct();
    nav.goTo('/products');
  } catch (error) {
    store.setMessage(error instanceof Error ? error.message : 'Не удалось сохранить товар.', 'error');
  }
}

onMounted(async () => {
  if (store.categoriesFlat.value.length === 0) {
    await store.fetchCategories();
  }

  if (nav.routeName.value === 'product-create') {
    store.resetProductForm();
  }
});
</script>
