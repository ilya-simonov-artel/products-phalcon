<template>
  <section class="panel span-2">
    <div class="panel-header">
      <h2>Категории</h2>
      <button @click="$emit('reset')">Новая категория</button>
    </div>
    <div class="category-layout">
      <ul class="tree">
        <CategoryNode
          v-for="node in categories"
          :key="node.id"
          :node="node"
          @edit="$emit('edit', $event)"
          @remove="$emit('remove', $event)"
        />
      </ul>
    </div>
  </section>
</template>

<script lang="ts" setup>
import { defineProps } from 'vue';
import CategoryNode from './CategoryNode.vue';
const props = defineProps<{ categories: any[] }>();
</script>

