import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const REFRESH_INTERVAL_MS = 30000;
const MIN_REFRESH_GAP_MS = 5000;

const hasActiveTextInput = (): boolean => {
  const active = document.activeElement as HTMLElement | null;
  if (!active) return false;

  const tagName = active.tagName?.toLowerCase();
  if (tagName === 'textarea' || tagName === 'select') return true;
  if (tagName === 'input') return true;
  return active.isContentEditable;
};

export const useAutoRefresh = () => {
  let intervalId: ReturnType<typeof setInterval> | null = null;
  let lastRefreshAt = 0;

  const runRefresh = () => {
    const now = Date.now();
    if (document.visibilityState !== 'visible') return;
    if (navigator.onLine === false) return;
    if (hasActiveTextInput()) return;
    if (now - lastRefreshAt < MIN_REFRESH_GAP_MS) return;

    lastRefreshAt = now;
    router.reload({
      preserveState: true,
      preserveScroll: true,
    });
  };

  const onWindowFocus = () => runRefresh();
  const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      runRefresh();
    }
  };

  onMounted(() => {
    intervalId = setInterval(runRefresh, REFRESH_INTERVAL_MS);
    window.addEventListener('focus', onWindowFocus);
    document.addEventListener('visibilitychange', onVisibilityChange);
  });

  onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
    window.removeEventListener('focus', onWindowFocus);
    document.removeEventListener('visibilitychange', onVisibilityChange);
  });
};
