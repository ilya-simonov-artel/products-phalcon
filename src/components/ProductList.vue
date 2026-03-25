<template>
  <section class="panel span-2">
    <div class="panel-header">
      <h2>Товары</h2>
      <button @click="$emit('create')">Добавить товар</button>
    </div>

    <div class="table-wrap">
      <table class="product-table">
        <thead>
          <tr>
            <th class="product-table__head product-table__cell product-table__cell--name">Название</th>
            <th class="product-table__head product-table__cell product-table__cell--description">Описание</th>
            <th class="product-table__head product-table__cell product-table__cell--price">Цена</th>
            <th class="product-table__head product-table__cell product-table__cell--category">Категория</th>
            <th class="product-table__head product-table__cell product-table__cell--stock">Наличие</th>
            <th class="product-table__head product-table__cell product-table__cell--actions"></th>
          </tr>
        </thead>
        <tbody class="product-table__body">
          <tr v-for="product in products" :key="product.id" class="product-table__row">
            <td class="product-table__cell product-table__cell--name">{{ product.name }}</td>
            <td class="product-table__cell product-table__cell--description">{{ product.content }}</td>
            <td class="product-table__cell product-table__cell--price">{{ money(product.price) }}</td>
            <td class="product-table__cell product-table__cell--category">{{ product.category }}</td>
            <td class="product-table__cell product-table__cell--stock">
              <span :class="['badge', product.in_stock ? 'badge--ok' : 'badge--muted']">{{ product.in_stock ? 'В наличии' : 'Нет' }}</span>
            </td>
            <td class="product-table__cell product-table__cell--actions">
              <div class="actions">
                <button @click="$emit('edit', product)">Редактировать</button>
                <button class="danger" @click="$emit('remove', product.id)">Удалить</button>
              </div>
            </td>
          </tr>
          <tr v-if="products.length === 0">
            <td colspan="6" class="product-table__cell product-table__cell--empty">По текущим фильтрам ничего не найдено.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pager">
      <button :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">Назад</button>

      <div class="pager__pages">
        <button
          v-for="item in paginationItems"
          :key="item.key"
          :disabled="item.type !== 'page'"
          :class="['pager__page', { 'pager__page--active': item.type === 'page' && item.value === currentPage }]"
          @click="item.type === 'page' && changePage(item.value)"
        >
          {{ item.label }}
        </button>
      </div>

      <span>Страница {{ currentPage }} / {{ totalPages }} · Всего {{ pagination.total }}</span>
      <button :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)">Вперед</button>
    </div>
  </section>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

const props = defineProps<{ products: any[]; pagination: any; filters: any }>();
const emit = defineEmits<{
  (event: 'create'): void;
  (event: 'edit', product: any): void;
  (event: 'remove', id: number): void;
  (event: 'change-page', page: number): void;
}>();

const totalPages = computed(() => Math.max(1, Number(props.pagination.pages) || 1));
const currentPage = computed(() => {
  const page = Number(props.pagination.page) || 1;
  return Math.min(Math.max(page, 1), totalPages.value);
});

type PaginationItem = { key: string; type: 'page' | 'ellipsis'; value: number; label: string };

const paginationItems = computed<PaginationItem[]>(() => {
  const pages = totalPages.value;
  const page = currentPage.value;
  const siblingCount = 1;

  const start = Math.max(1, page - siblingCount);
  const end = Math.min(pages, page + siblingCount);

  const pageNumbers = new Set<number>([1, pages]);

  for (let value = start; value <= end; value += 1) {
    pageNumbers.add(value);
  }

  // Расширяем окно у границ, чтобы пагинация выглядела естественно.
  if (start <= 3) {
    pageNumbers.add(2);
    pageNumbers.add(3);
  }

  if (end >= pages - 2) {
    pageNumbers.add(pages - 1);
    pageNumbers.add(pages - 2);
  }

  const sortedPages = Array.from(pageNumbers)
    .filter((value) => value >= 1 && value <= pages)
    .sort((a, b) => a - b);

  const items: PaginationItem[] = [];

  sortedPages.forEach((value, index) => {
    const prev = sortedPages[index - 1];
    if (prev && value - prev > 1) {
      items.push({
        key: `ellipsis-${prev}-${value}`,
        type: 'ellipsis',
        value: 0,
        label: '…'
      });
    }

    items.push({
      key: `page-${value}`,
      type: 'page',
      value,
      label: String(value)
    });
  });

  return items;
});

function changePage(page: number) {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return;
  emit('change-page', page);
}

function money(value: number) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(value || 0);
}
</script>
