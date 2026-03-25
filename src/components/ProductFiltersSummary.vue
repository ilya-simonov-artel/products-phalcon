<template>
  <section class="panel span-2">
    <div class="panel-header">
      <h2>Фильтры и сводка</h2>
      <button @click="$emit('reset')">Сбросить</button>
    </div>

    <div class="filters">
      <label v-for="config in levelConfigs" :key="config.level">
        {{ config.label }}
        <select :value="levelValue(config.level)" @change="onLevelChange(config.level, $event)">
          <option value="">{{ config.level === 0 ? 'Все категории' : 'Все подкатегории' }}</option>
          <option v-for="category in config.options" :key="category.id" :value="String(category.id)">
            {{ category.name }}
          </option>
        </select>
      </label>

      <label>
        Наличие
        <select :value="filters.in_stock" @change="onInStockChange">
          <option value="">Все</option>
          <option value="true">Только в наличии</option>
          <option value="false">Только отсутствующие</option>
        </select>
      </label>

      <label>
        На странице
        <select :value="String(pagination.limit)" @change="onLimitChange">
          <option value="5">5</option>
          <option value="10">10</option>
          <option value="20">20</option>
        </select>
      </label>
    </div>

    <div class="aggregate cards">
      <article>
        <span>Товаров в наличии</span>
        <strong>{{ aggregate.in_stock_count }}</strong>
      </article>
      <article>
        <span>Суммарная стоимость</span>
        <strong>{{ money(aggregate.in_stock_total_value) }}</strong>
      </article>
    </div>
  </section>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue';
import type { CategoryFlat } from '../stores/appStore';

type LevelConfig = { level: number; label: string; options: CategoryFlat[] };

const props = defineProps<{
  categories: CategoryFlat[];
  filters: { category_id: string; in_stock: string };
  pagination: { limit: number };
  aggregate: { in_stock_count: number; in_stock_total_value: number };
}>();

const emit = defineEmits<{
  (event: 'reset'): void;
  (event: 'update:category', value: string): void;
  (event: 'update:in-stock', value: string): void;
  (event: 'update:limit', value: number): void;
}>();

const selectedPath = ref<string[]>(['']);

const categoryMap = computed(() => new Map(props.categories.map((category) => [String(category.id), category])));

const childrenMap = computed(() => {
  const map = new Map<string, CategoryFlat[]>();

  for (const category of props.categories) {
    const parentKey = category.parent_id === null ? 'root' : String(category.parent_id);
    const list = map.get(parentKey) || [];
    list.push(category);
    map.set(parentKey, list);
  }

  for (const [key, value] of map.entries()) {
    map.set(
      key,
      [...value].sort((a, b) => a.name.localeCompare(b.name, 'ru')),
    );
  }

  return map;
});

const levelConfigs = computed<LevelConfig[]>(() => {
  const configs: LevelConfig[] = [];
  const roots = childrenMap.value.get('root') || [];
  configs.push({ level: 0, label: 'Категория', options: roots });

  let parentId = selectedPath.value[0] || '';
  let level = 1;

  while (parentId) {
    const children = childrenMap.value.get(parentId) || [];
    if (children.length === 0) break;

    const label = level === 1 ? 'Подкатегория' : `Подкатегория ${level}`;
    configs.push({ level, label, options: children });

    parentId = selectedPath.value[level] || '';
    level += 1;
  }

  return configs;
});

function syncSelectionFromFilter(filterCategoryId: string) {
  if (!filterCategoryId) {
    selectedPath.value = [''];
    return;
  }

  const activeCategory = categoryMap.value.get(filterCategoryId);
  if (!activeCategory) {
    selectedPath.value = [''];
    return;
  }

  const chain: string[] = [];
  let current: CategoryFlat | undefined = activeCategory;

  while (current) {
    chain.push(String(current.id));
    current = current.parent_id === null ? undefined : categoryMap.value.get(String(current.parent_id));
  }

  selectedPath.value = chain.reverse();
}

function currentCategoryFilter(): string {
  let value = '';

  for (const categoryId of selectedPath.value) {
    if (!categoryId) break;
    value = categoryId;
  }

  return value;
}

function levelValue(level: number) {
  return selectedPath.value[level] || '';
}

function onLevelChange(level: number, event: Event) {
  const target = event.target as HTMLSelectElement;
  const value = target.value;

  const nextPath = selectedPath.value.slice(0, level + 1);
  nextPath[level] = value;

  if (level === 0 && !value) {
    selectedPath.value = [''];
  } else {
    selectedPath.value = nextPath;
  }

  emit('update:category', currentCategoryFilter());
}

function onInStockChange(event: Event) {
  const target = event.target as HTMLSelectElement;
  emit('update:in-stock', target.value);
}

function onLimitChange(event: Event) {
  const target = event.target as HTMLSelectElement;
  emit('update:limit', Number(target.value));
}

watch(
  () => [props.filters.category_id, props.categories] as const,
  ([categoryId]) => {
    syncSelectionFromFilter(categoryId);
  },
  { immediate: true },
);

function money(value: number) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(value || 0);
}
</script>
