<template>
  <div class="availability-calendar">
    <!-- En-tête du calendrier -->
    <div class="flex items-center justify-between mb-2">
      <button
        @click="previousMonth"
        class="p-1 hover:bg-slate-100 rounded transition-colors"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>
      <h3 class="text-sm font-semibold text-slate-900 capitalize">
        {{ monthName }} {{ year }}
      </h3>
      <button
        @click="nextMonth"
        class="p-1 hover:bg-slate-100 rounded transition-colors"
      >
        <ChevronRight class="w-4 h-4" />
      </button>
    </div>

    <!-- Jours de la semaine -->
    <div class="grid grid-cols-7 gap-0.5 mb-1">
      <div
        v-for="day in weekDays"
        :key="day"
        class="text-center text-[10px] font-medium text-slate-500 py-0.5"
      >
        {{ day }}
      </div>
    </div>

    <!-- Grille du calendrier -->
    <div class="grid grid-cols-7 gap-0.5">
      <!-- Jours vides avant le premier jour du mois -->
      <div
        v-for="n in firstDayOfMonth"
        :key="`empty-${n}`"
        class="h-7"
      ></div>

      <!-- Jours du mois -->
      <div
        v-for="day in daysInMonth"
        :key="day"
        @click="selectDate(day)"
        class="h-7 flex items-center justify-center text-xs rounded cursor-pointer transition-colors relative"
        :class="getDayClass(day)"
      >
        <span>{{ day }}</span>
        <!-- Indicateur de réservation -->
        <div
          v-if="hasBooking(day) && !isDateBlocked(day)"
          class="absolute bottom-0.5 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-600 rounded-full"
        ></div>
        <!-- Indicateur de date bloquée -->
        <div
          v-if="isDateBlocked(day)"
          class="absolute bottom-0.5 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-red-600 rounded-full"
        ></div>
      </div>
    </div>

    <!-- Légende -->
    <div class="mt-2 pt-2 border-t border-slate-200">
      <div class="flex flex-wrap gap-2.5 text-[10px]">
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 bg-slate-100 border border-slate-300 rounded"></div>
          <span class="text-slate-600">Disponible</span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 bg-blue-100 border border-blue-300 rounded"></div>
          <span class="text-slate-600">Réservé</span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 bg-amber-100 border border-amber-300 rounded"></div>
          <span class="text-slate-600">En attente</span>
        </div>
        <div v-if="editable" class="flex items-center gap-1.5">
          <div class="w-3 h-3 bg-red-100 border border-red-300 rounded"></div>
          <span class="text-slate-600">Bloqué</span>
        </div>
      </div>
      <div v-if="editable" class="mt-2 text-[10px] text-slate-500 text-center">
        Cliquez sur une date pour la bloquer/débloquer
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
  bookings?: Array<{
    id: number | string;
    startDate?: string;
    endDate?: string;
    status?: string;
    statusRaw?: string;
  }>;
  propertyId?: string | number;
  propertyType?: 'residence' | 'vehicle' | 'offer';
  blockedDates?: string[];
  editable?: boolean;
}>();

const currentDate = ref(new Date());
const selectedDate = ref<Date | null>(null);
const blockedDatesList = ref<Set<string>>(new Set());
const loading = ref(false);

const weekDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

const monthName = computed(() => {
  return currentDate.value.toLocaleDateString('fr-FR', { month: 'long' });
});

const year = computed(() => {
  return currentDate.value.getFullYear();
});

const firstDayOfMonth = computed(() => {
  const firstDay = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1);
  const dayOfWeek = firstDay.getDay();
  // Convertir dimanche (0) en 6, lundi (1) en 0, etc.
  return dayOfWeek === 0 ? 6 : dayOfWeek - 1;
});

const daysInMonth = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();
  return new Date(year, month + 1, 0).getDate();
});

// Convertir les dates de réservation en objets Date pour comparaison
const bookingDates = computed(() => {
  const dates: Map<number, { status: string; isStart: boolean; isEnd: boolean }> = new Map();
  
  if (!props.bookings) return dates;
  
  props.bookings.forEach(booking => {
    // Essayer plusieurs champs pour les dates
    const startDateStr = booking.startDate || booking.start_date || booking.checkInDate || booking.check_in_date;
    const endDateStr = booking.endDate || booking.end_date || booking.checkOutDate || booking.check_out_date;
    
    if (!startDateStr || !endDateStr) {
      console.warn('Réservation sans dates valides', booking);
      return;
    }
    
    try {
      // Parser les dates (peuvent être ISO string ou format local)
      let start = new Date(startDateStr);
      let end = new Date(endDateStr);
      
      // Si les dates sont invalides, essayer de les parser autrement
      if (isNaN(start.getTime())) {
        // Essayer de parser un format comme "18 déc 2024"
        start = parseDateString(startDateStr);
      }
      if (isNaN(end.getTime())) {
        end = parseDateString(endDateStr);
      }
      
      // Vérifier que les dates sont valides
      if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        console.warn('Dates invalides pour la réservation', {
          bookingId: booking.id,
          startDateStr,
          endDateStr,
          start,
          end
        });
        return;
      }
      
      // Normaliser les dates à minuit pour comparer seulement les jours
      start.setHours(0, 0, 0, 0);
      end.setHours(0, 0, 0, 0);
      
      const status = normalizeCalendarStatus(booking.statusRaw || booking.status || 'pending');
      
      // Ignorer les réservations annulées/échouées/expirées
      if (status === 'cancelled') {
        return;
      }
      
      // Marquer tous les jours entre start et end
      const current = new Date(start);
      while (current <= end) {
        const dateKey = getDateKey(current);
        const isStart = current.getTime() === start.getTime();
        const isEnd = current.getTime() === end.getTime();
        
        if (!dates.has(dateKey) || isStart || isEnd) {
          dates.set(dateKey, { status, isStart, isEnd });
        }
        
        current.setDate(current.getDate() + 1);
      }
    } catch (e) {
      console.error('Erreur lors du traitement d\'une réservation', {
        booking,
        error: e
      });
    }
  });
  
  return dates;
});

// Fonction helper pour parser les dates au format français
const parseDateString = (dateStr: string): Date => {
  // Essayer différents formats
  const formats = [
    // ISO format
    (str: string) => new Date(str),
    // Format français "18 déc 2024"
    (str: string) => {
      const months: Record<string, number> = {
        // Abréviations courtes (uniques)
        'jan': 0, 'fév': 1, 'mar': 2, 'avr': 3, 'mai': 4, 'jun': 5,
        'jul': 6, 'aoû': 7, 'sep': 8, 'oct': 9, 'nov': 10, 'déc': 11,
        // Noms complets (uniques)
        'janvier': 0, 'février': 1, 'mars': 2, 'avril': 3, 'juin': 5,
        'juillet': 6, 'août': 7, 'septembre': 8, 'octobre': 9, 'novembre': 10, 'décembre': 11,
      };
      
      const parts = str.trim().split(' ');
      if (parts.length >= 3) {
        const day = parseInt(parts[0]);
        const monthStr = parts[1].toLowerCase();
        const year = parseInt(parts[2]);
        
        if (months[monthStr] !== undefined && !isNaN(day) && !isNaN(year)) {
          return new Date(year, months[monthStr], day);
        }
      }
      return new Date(NaN);
    },
  ];
  
  for (const format of formats) {
    try {
      const date = format(dateStr);
      if (!isNaN(date.getTime())) {
        return date;
      }
    } catch (e) {
      // Continuer avec le format suivant
    }
  }
  
  return new Date(NaN);
};

const getDateKey = (date: Date): number => {
  return date.getFullYear() * 10000 + (date.getMonth() + 1) * 100 + date.getDate();
};

const getCurrentMonthDateKey = (day: number): number => {
  const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
  return getDateKey(date);
};

const normalizeCalendarStatus = (rawStatus: string): 'confirmed' | 'pending' | 'cancelled' => {
  const status = (rawStatus || '').toLowerCase().trim();

  if (
    status === 'cancelled' ||
    status === 'canceled' ||
    status === 'annulee' ||
    status === 'annulée' ||
    status === 'failed' ||
    status === 'echec' ||
    status === 'échec' ||
    status === 'expired' ||
    status === 'expiree' ||
    status === 'expirée'
  ) {
    return 'cancelled';
  }

  if (
    status === 'pending' ||
    status === 'en attente' ||
    status === 'en_attente' ||
    status === 'awaiting_payment' ||
    status === 'awaitingpayment'
  ) {
    return 'pending';
  }

  return 'confirmed';
};

const hasBooking = (day: number): boolean => {
  const dateKey = getCurrentMonthDateKey(day);
  return bookingDates.value.has(dateKey);
};

// Vérifier si une date a une réservation active (non annulée)
const hasActiveBooking = (day: number): boolean => {
  const dateKey = getCurrentMonthDateKey(day);
  const booking = bookingDates.value.get(dateKey);
  if (!booking) return false;
  
  const status = normalizeCalendarStatus(booking.status || '');
  return status !== 'cancelled';
};

const getBookingStatus = (day: number): string | null => {
  const dateKey = getCurrentMonthDateKey(day);
  const booking = bookingDates.value.get(dateKey);
  return booking?.status || null;
};

const getDayClass = (day: number): string => {
  const today = new Date();
  const isToday = 
    day === today.getDate() &&
    currentDate.value.getMonth() === today.getMonth() &&
    currentDate.value.getFullYear() === today.getFullYear();
  
  const dateKey = getCurrentMonthDateKey(day);
  const booking = bookingDates.value.get(dateKey);
  const isBlocked = isDateBlocked(day);
  
  // Date bloquée (priorité la plus haute)
  if (isBlocked) {
    return isToday
      ? 'bg-red-200 border-2 border-red-600 text-red-900 font-semibold'
      : 'bg-red-100 border border-red-300 text-red-900 hover:bg-red-200';
  }
  
  if (booking) {
    const status = normalizeCalendarStatus(booking.status || '');
    if (status === 'confirmed') {
      return isToday 
        ? 'bg-blue-200 border-2 border-blue-600 text-blue-900 font-semibold'
        : 'bg-blue-100 border border-blue-300 text-blue-900 hover:bg-blue-200';
    } else if (status === 'pending') {
      return isToday
        ? 'bg-amber-200 border-2 border-amber-600 text-amber-900 font-semibold'
        : 'bg-amber-100 border border-amber-300 text-amber-900 hover:bg-amber-200';
    } else if (status === 'cancelled') {
      return isToday
        ? 'bg-slate-200 border-2 border-slate-600 text-slate-700 font-semibold'
        : 'bg-slate-100 border border-slate-300 text-slate-700 hover:bg-slate-200';
    }
  }
  
  // Jour disponible
  if (isToday) {
    return 'bg-blue-50 border-2 border-blue-600 text-blue-900 font-semibold';
  }
  
  return 'bg-slate-50 border border-slate-200 text-slate-900 hover:bg-slate-100';
};

const previousMonth = () => {
  const newDate = new Date(currentDate.value);
  newDate.setMonth(newDate.getMonth() - 1);
  currentDate.value = newDate;
};

const nextMonth = () => {
  const newDate = new Date(currentDate.value);
  newDate.setMonth(newDate.getMonth() + 1);
  currentDate.value = newDate;
};

// Charger les dates bloquées au montage
onMounted(() => {
  if (props.blockedDates && props.blockedDates.length > 0) {
    blockedDatesList.value = new Set(props.blockedDates);
  } else if (props.propertyId && props.propertyType) {
    loadBlockedDates();
  }
});

// Surveiller les changements de blockedDates
watch(() => props.blockedDates, (newDates) => {
  if (newDates) {
    blockedDatesList.value = new Set(newDates);
  }
});

// Charger les dates bloquées depuis l'API
const loadBlockedDates = async () => {
  if (!props.propertyId || !props.propertyType) return;
  
  try {
    const response = await axios.get(`/owner/${props.propertyType}s/${props.propertyId}/blocked-dates`);
    if (response.data?.blockedDates) {
      blockedDatesList.value = new Set(response.data.blockedDates);
    }
  } catch (error) {
    console.error('Erreur lors du chargement des dates bloquées:', error);
  }
};

// Vérifier si une date est bloquée
const isDateBlocked = (day: number): boolean => {
  const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
  const dateStr = formatDateForApi(date);
  return blockedDatesList.value.has(dateStr);
};

// Formater une date pour l'API (YYYY-MM-DD)
const formatDateForApi = (date: Date): string => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Bloquer ou débloquer une date
const toggleDateBlock = async (day: number) => {
  if (!props.editable || !props.propertyId || !props.propertyType) {
    // Si non éditable, juste émettre l'événement
    const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
    emit('date-selected', date);
    return;
  }

  const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
  const dateStr = formatDateForApi(date);
  const isBlocked = blockedDatesList.value.has(dateStr);

  loading.value = true;
  
  // Mise à jour optimiste de l'interface
  const previousBlockedState = isBlocked;
  if (isBlocked) {
    blockedDatesList.value.delete(dateStr);
  } else {
    blockedDatesList.value.add(dateStr);
  }

  try {
    const endpoint = `/owner/${props.propertyType}s/${props.propertyId}/blocked-dates`;
    
    if (previousBlockedState) {
      // Débloquer la date - passer la date en paramètre de requête
      await axios.delete(`${endpoint}?date=${encodeURIComponent(dateStr)}`);
      emit('date-unblocked', date);
    } else {
      // Bloquer la date - le backend vérifiera les réservations
      await axios.post(endpoint, { date: dateStr }, {
        headers: {
          'Content-Type': 'application/json'
        }
      });
      emit('date-blocked', date);
    }
  } catch (error: any) {
    console.error('Erreur lors du blocage/déblocage de la date:', error);
    
    // Revenir à l'état précédent en cas d'erreur
    if (previousBlockedState) {
      blockedDatesList.value.add(dateStr);
    } else {
      blockedDatesList.value.delete(dateStr);
    }
    
    // Afficher le message d'erreur du backend
    const errorMessage = error.response?.data?.error 
      || error.response?.data?.message 
      || error.message 
      || 'Erreur lors de la modification de la date';
    alert(errorMessage);
  } finally {
    loading.value = false;
  }
};

const selectDate = (day: number) => {
  if (props.editable) {
    toggleDateBlock(day);
  } else {
    const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
    selectedDate.value = date;
    emit('date-selected', date);
  }
};

const emit = defineEmits<{
  'date-selected': [date: Date];
  'date-blocked': [date: Date];
  'date-unblocked': [date: Date];
}>();
</script>

