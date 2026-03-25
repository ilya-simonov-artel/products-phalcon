import { computed, ref } from 'vue';
import { useAppStore } from './appStore';

export type RouteName = 'login' | 'products' | 'product-create' | 'product-edit' | 'categories' | 'category-create' | 'category-edit';

const path = ref(window.location.hash.replace(/^#/, '') || '/login');

function parseRouteName(currentPath: string): RouteName {
  if (currentPath === '/login') return 'login';
  if (currentPath === '/products') return 'products';
  if (currentPath === '/products/new') return 'product-create';
  if (/^\/products\/\d+\/edit$/.test(currentPath)) return 'product-edit';
  if (currentPath === '/categories') return 'categories';
  if (currentPath === '/categories/new') return 'category-create';
  if (/^\/categories\/\d+\/edit$/.test(currentPath)) return 'category-edit';
  return 'products';
}

function normalizeRoute(nextPath: string): string {
  if (!nextPath.startsWith('/')) return '/login';
  return nextPath;
}

function syncByAuth(nextPath: string): string {
  const store = useAppStore();
  const name = parseRouteName(nextPath);
  const requiresAuth = name !== 'login';

  if (requiresAuth && !store.isAuthenticated.value) {
    return '/login';
  }

  if (name === 'login' && store.isAuthenticated.value) {
    return '/products';
  }

  return nextPath;
}

function setPath(nextPath: string, replace = false) {
  const normalized = syncByAuth(normalizeRoute(nextPath));
  path.value = normalized;
  const hash = `#${normalized}`;

  if (replace) {
    window.location.replace(hash);
  } else if (window.location.hash !== hash) {
    window.location.hash = hash;
  }
}

window.addEventListener('hashchange', () => {
  path.value = syncByAuth(normalizeRoute(window.location.hash.replace(/^#/, '') || '/login'));
});

export function useNavigation() {
  const routeName = computed(() => parseRouteName(path.value));

  return {
    path,
    routeName,
    goTo: (nextPath: string) => setPath(nextPath),
    ensureCurrent: () => setPath(path.value, true),
    isActive: (target: string) => path.value === target,
  };
}
