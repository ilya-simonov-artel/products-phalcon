<template>
  <li>
    <div class="tree-node">
      <span class="tree-node__title">{{ node.name }}</span>
      <div class="actions">
        <button @click="$emit('edit', node)">Редактировать</button>
        <button class="danger" @click="$emit('remove', node.id)">Удалить</button>
      </div>
    </div>
    <ul v-if="node.children && node.children.length">
      <CategoryNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        @edit="$emit('edit', $event)"
        @remove="$emit('remove', $event)"
      />
    </ul>
  </li>
</template>

<script lang="ts" setup>
import { defineProps } from 'vue';

defineProps<{ node: any }>();
</script>
