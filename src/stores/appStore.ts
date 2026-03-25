import axios, { AxiosError } from 'axios';
import { computed, reactive, ref } from 'vue';

export type CategoryNode = { id: number; name: string; parent_id: number | null; children: CategoryNode[] };
export type CategoryFlat = { id: number; name: string; parent_id: number | null; full_name: string };
export type Product = {
  id: number;
  name: string;
  content: string;
  price: number;
  category_id: number;
  category: string;
  in_stock: boolean;
};
export type User = { id: number; username: string; display_name: string };
type AppError = Error & { status?: number; details?: Record<string, string> };

const TOKEN_KEY = 'auth_token';
const http = axios.create();
const STATUS_MESSAGES: Record<number, string> = {
  400: 'Некорректный запрос. Проверьте корректность отправленных данных.',
  401: 'Требуется авторизация. Выполните вход в систему.',
  403: 'Доступ запрещён. У вас недостаточно прав для этого действия.',
  404: 'Запрошенный объект не найден.',
  409: 'Операцию нельзя выполнить из-за текущего состояния данных.',
  422: 'Проверьте заполнение формы. Некоторые поля содержат ошибки.',
  500: 'Внутренняя ошибка сервера. Повторите попытку позже.',
};

const token = ref(localStorage.getItem(TOKEN_KEY) || '');
const authUser = ref<User | null>(null);
const credentials = reactive({ username: 'demo', password: 'test12345' });
const message = reactive({ text: '', type: 'info' as 'success' | 'error' | 'info' });
const loading = reactive({
  login: false,
  productSubmit: false,
  categorySubmit: false,
});
const formErrors = reactive({
  login: {} as Record<string, string>,
  product: {} as Record<string, string>,
  category: {} as Record<string, string>,
});

const filters = reactive({ category_id: '', in_stock: '' });
const pagination = reactive({ page: 1, limit: 10, total: 0, pages: 0 });
const aggregate = reactive({ in_stock_count: 0, in_stock_total_value: 0 });

const categoriesTree = ref<CategoryNode[]>([]);
const categoriesFlat = ref<CategoryFlat[]>([]);
const products = ref<Product[]>([]);

const productForm = reactive({
  id: null as number | null,
  name: '',
  content: '',
  price: 0,
  category_id: '' as number | '',
  in_stock: true,
});

const categoryForm = reactive({
  id: null as number | null,
  name: '',
  parent_id: '' as number | '',
});

const isAuthenticated = computed(() => token.value.trim().length > 0 && authUser.value !== null);

http.interceptors.request.use((config) => {
  const jwt = token.value.trim();
  if (jwt) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${jwt}`;
  }

  return config;
});

function setMessage(text: string, type: 'success' | 'error' | 'info' = 'info') {
  message.text = text;
  message.type = type;
}

function clearData() {
  categoriesTree.value = [];
  categoriesFlat.value = [];
  products.value = [];
  pagination.total = 0;
  pagination.pages = 0;
  aggregate.in_stock_count = 0;
  aggregate.in_stock_total_value = 0;
}

async function api<T>(url: string, init?: RequestInit): Promise<T> {
  try {
    const response = await http.request({
      url,
      method: init?.method || 'GET',
      headers: init?.headers as Record<string, string> | undefined,
      data: init?.body ? JSON.parse(String(init.body)) : undefined,
    });
    const payload = response.data;

    if (!payload?.success) {
      const error = new Error(buildReadableErrorMessage(response.status, payload?.message)) as AppError;
      error.status = response.status;
      error.details = payload?.details && typeof payload.details === 'object' ? payload.details : undefined;
      throw error;
    }

    return payload.data as T;
  } catch (error) {
    if (axios.isAxiosError(error)) {
      const axiosError = error as AxiosError<{ message?: string; details?: Record<string, string> }>;
      const status = axiosError.response?.status;
      const message = buildReadableErrorMessage(status, axiosError.response?.data?.message);
      const appError = new Error(message) as AppError;
      appError.status = status;
      appError.details = axiosError.response?.data?.details;
      throw appError;
    }

    throw error;
  }
}

function buildReadableErrorMessage(status?: number, backendMessage?: string): string {
  const cleanBackendMessage = (backendMessage || '').trim();
  if (cleanBackendMessage) return cleanBackendMessage;

  if (status && STATUS_MESSAGES[status]) {
    return STATUS_MESSAGES[status];
  }

  if (status) {
    return `Ошибка HTTP ${status}. Проверьте введённые данные и повторите попытку.`;
  }

  return 'Не удалось связаться с сервером. Проверьте соединение и попробуйте снова.';
}

async function login() {
  if (loading.login) return;
  loading.login = true;
  formErrors.login = {};
  try {
    const data = await api<{ token: string; user: User }>('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(credentials),
    });

    token.value = data.token;
    authUser.value = data.user;
    localStorage.setItem(TOKEN_KEY, data.token);
    setMessage('Вход выполнен.', 'success');
    await refreshAll();
  } catch (error) {
    const details = error && typeof error === 'object' && 'details' in error ? (error as AppError).details : undefined;
    formErrors.login = details || { _common: error instanceof Error ? error.message : 'Не удалось выполнить вход.' };
    throw error;
  } finally {
    loading.login = false;
  }
}

async function restoreSession() {
  if (!token.value) return;

  try {
    const data = await api<{ user: User }>('/api/auth/me');
    authUser.value = data.user;
    await refreshAll();
  } catch {
    token.value = '';
    authUser.value = null;
    localStorage.removeItem(TOKEN_KEY);
    clearData();
  }
}

async function logout() {
  try {
    if (token.value) {
      await api('/api/auth/logout', { method: 'POST' });
    }
  } finally {
    token.value = '';
    authUser.value = null;
    localStorage.removeItem(TOKEN_KEY);
    clearData();
    setMessage('Вы вышли из системы.', 'info');
  }
}

async function fetchCategories() {
  const data = await api<{ tree: CategoryNode[]; items: CategoryFlat[] }>('/api/categories');
  categoriesTree.value = data.tree;
  categoriesFlat.value = data.items;
}

async function fetchProducts() {
  const params = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) });
  if (filters.category_id) params.set('category_id', filters.category_id);
  if (filters.in_stock) params.set('in_stock', filters.in_stock);

  const data = await api<{ items: Product[]; pagination: typeof pagination }>(`/api/products?${params.toString()}`, {
  });

  products.value = data.items;
  pagination.total = Number(data.pagination.total || 0);
  pagination.pages = Number(data.pagination.pages || 0);
}

async function fetchAggregate() {
  const params = new URLSearchParams();
  if (filters.category_id) params.set('category_id', filters.category_id);

  const data = await api<typeof aggregate>(`/api/products/aggregate${params.toString() ? `?${params.toString()}` : ''}`, {
  });

  aggregate.in_stock_count = Number(data.in_stock_count || 0);
  aggregate.in_stock_total_value = Number(data.in_stock_total_value || 0);
}

async function refreshAll() {
  if (!isAuthenticated.value) return;

  try {
    await Promise.all([fetchCategories(), fetchProducts(), fetchAggregate()]);
  } catch (error) {
    const text = error instanceof Error ? error.message : 'Не удалось обновить данные.';
    const status = error && typeof error === 'object' && 'status' in error ? Number((error as { status?: number }).status) : 0;

    if (status === 401 || status === 403) {
      await logout();
      setMessage('Сессия истекла. Выполните вход снова.', 'error');
      return;
    }

    setMessage(text, 'error');
  }
}

function resetProductForm() {
  productForm.id = null;
  productForm.name = '';
  productForm.content = '';
  productForm.price = 0;
  productForm.category_id = '';
  productForm.in_stock = true;
  formErrors.product = {};
}

function editProduct(product: Product) {
  productForm.id = product.id;
  productForm.name = product.name;
  productForm.content = product.content;
  productForm.price = product.price;
  productForm.category_id = product.category_id;
  productForm.in_stock = Boolean(product.in_stock);
}

async function submitProduct() {
  if (loading.productSubmit) return;
  loading.productSubmit = true;
  formErrors.product = {};
  const payload = {
    name: productForm.name,
    content: productForm.content,
    price: productForm.price,
    category_id: Number(productForm.category_id),
    in_stock: productForm.in_stock,
  };

  try {
    if (productForm.id) {
      await api(`/api/products/${productForm.id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setMessage('Товар обновлён.', 'success');
    } else {
      await api('/api/products', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setMessage('Товар создан.', 'success');
    }

    resetProductForm();
    await Promise.all([fetchProducts(), fetchAggregate()]);
  } catch (error) {
    const details = error && typeof error === 'object' && 'details' in error ? (error as AppError).details : undefined;
    formErrors.product = details || {};
    throw error;
  } finally {
    loading.productSubmit = false;
  }
}

async function removeProduct(id: number) {
  await api(`/api/products/${id}`, { method: 'DELETE' });
  setMessage('Товар удалён.', 'success');
  await Promise.all([fetchProducts(), fetchAggregate()]);
}

function changePage(page: number) {
  if (page < 1 || (pagination.pages && page > pagination.pages)) return;
  pagination.page = page;

  return fetchProducts();
}

function applyProductFilters() {
  if (!isAuthenticated.value) return Promise.resolve();

  pagination.page = 1;

  return Promise.all([fetchProducts(), fetchAggregate()]).then(() => undefined);
}

function resetCategoryForm() {
  categoryForm.id = null;
  categoryForm.name = '';
  categoryForm.parent_id = '';
  formErrors.category = {};
}

function editCategory(category: CategoryFlat | CategoryNode) {
  categoryForm.id = category.id;
  categoryForm.name = category.name;
  categoryForm.parent_id = (category.parent_id ?? '') as number | '';
}

async function submitCategory() {
  if (loading.categorySubmit) return;
  loading.categorySubmit = true;
  formErrors.category = {};
  const payload = {
    name: categoryForm.name,
    parent_id: categoryForm.parent_id === '' ? null : Number(categoryForm.parent_id),
  };

  try {
    if (categoryForm.id) {
      await api(`/api/categories/${categoryForm.id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setMessage('Категория обновлена.', 'success');
    } else {
      await api('/api/categories', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setMessage('Категория создана.', 'success');
    }

    resetCategoryForm();
    await refreshAll();
  } catch (error) {
    const details = error && typeof error === 'object' && 'details' in error ? (error as AppError).details : undefined;
    formErrors.category = details || {};
    throw error;
  } finally {
    loading.categorySubmit = false;
  }
}

async function removeCategory(id: number) {
  await api(`/api/categories/${id}`, { method: 'DELETE' });
  setMessage('Категория удалена.', 'success');
  await refreshAll();
}

export function useAppStore() {
  return {
    token,
    authUser,
    credentials,
    message,
    loading,
    formErrors,
    filters,
    pagination,
    aggregate,
    categoriesTree,
    categoriesFlat,
    products,
    productForm,
    categoryForm,
    isAuthenticated,
    setMessage,
    login,
    restoreSession,
    logout,
    refreshAll,
    fetchProducts,
    fetchCategories,
    fetchAggregate,
    resetProductForm,
    editProduct,
    submitProduct,
    removeProduct,
    changePage,
    applyProductFilters,
    resetCategoryForm,
    editCategory,
    submitCategory,
    removeCategory,
  };
}
