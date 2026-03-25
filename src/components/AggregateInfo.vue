<template>
  <section class="panel">
    <div class="panel-header">
      <h2>Фильтры и сводка</h2>
      <button @click="$emit('refresh')">Обновить</button>
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
import { defineProps } from 'vue';
const props = defineProps<{ aggregate: { in_stock_count: number; in_stock_total_value: number } }>();
function money(value: number) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(value || 0);
}
</script>

