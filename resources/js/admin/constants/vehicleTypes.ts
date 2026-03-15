/**
 * Types de véhicules disponibles
 * Cette liste peut être étendue ou modifiée selon les besoins
 */
export const VEHICLE_TYPES = [
  { value: 'berline', label: 'Berline' },
  { value: 'suv', label: 'SUV' },
  { value: '4x4', label: '4x4' },
  { value: 'utilitaire', label: 'Utilitaire' },
  { value: 'moto', label: 'Moto' },
] as const;

export type VehicleType = typeof VEHICLE_TYPES[number]['value'];

/**
 * Obtenir le label d'un type de véhicule
 */
export function getVehicleTypeLabel(type: string): string {
  const vehicleType = VEHICLE_TYPES.find(vt => vt.value === type.toLowerCase());
  return vehicleType?.label || type;
}

