# Implémentation de l'endpoint Stats côté NestJS

## 📋 Contexte

Le dashboard Laravel appelle l'endpoint `/api/owner/stats` de l'API NestJS pour récupérer les statistiques de revenus d'un propriétaire. Cet endpoint doit être implémenté avec des **agrégations SQL performantes** pour éviter de charger des milliers de lignes en mémoire.

## 🔒 Sécurité Critique

**IMPORTANT** : L'endpoint doit **IGNORER** tout `ownerId` passé en paramètre et utiliser **uniquement** l'`ownerId` présent dans le token JWT. Cela garantit qu'un propriétaire ne peut pas accéder aux stats d'un autre.

## 📁 Structure à créer

### 1. Service de Statistiques

`src/stats/stats.service.ts`

```typescript
import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { startOfMonth, endOfMonth, subMonths, format } from 'date-fns';

@Injectable()
export class StatsService {
  constructor(private prisma: PrismaService) {}

  /**
   * Calcule les KPIs financiers pour un propriétaire spécifique
   * @param ownerId Identifiant unique du partenaire (depuis le token JWT)
   */
  async getOwnerStats(ownerId: string) {
    const now = new Date();
    const currentMonthStart = startOfMonth(now);
    const lastMonthStart = startOfMonth(subMonths(now, 1));
    const lastMonthEnd = endOfMonth(subMonths(now, 1));

    // 1. Revenu Total & Comparaison mensuelle
    // IMPORTANT: 
    // - Calculer 90% du prix total pour le propriétaire
    // - INCLURE uniquement les réservations confirmées par le propriétaire (ownerConfirmedAt existe)
    // - OU les réservations avec statut CONFIRMED/COMPLETED
    // - EXCLURE les réservations annulées et en attente (pending)
    const revenueData = await this.prisma.reservation.aggregate({
      where: { 
        residence: { ownerId },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } }, // Confirmée par le propriétaire
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      },
      _sum: { totalPrice: true },
    });

    const currentMonthRevenue = await this.prisma.reservation.aggregate({
      where: {
        residence: { ownerId },
        createdAt: { gte: currentMonthStart },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      },
      _sum: { totalPrice: true },
    });

    const lastMonthRevenue = await this.prisma.reservation.aggregate({
      where: {
        residence: { ownerId },
        createdAt: { 
          gte: lastMonthStart, 
          lte: lastMonthEnd 
        },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      },
      _sum: { totalPrice: true },
    });

    // Calculer 90% pour le propriétaire (10% commission plateforme)
    const totalRevenue = Math.round((revenueData._sum.totalPrice || 0) * 0.9);
    const currentMonthOwnerRevenue = Math.round((currentMonthRevenue._sum.totalPrice || 0) * 0.9);
    const lastMonthOwnerRevenue = Math.round((lastMonthRevenue._sum.totalPrice || 0) * 0.9);

    // 2. Nombre de réservations (uniquement confirmées ou terminées, exclure annulées et en attente)
    const totalBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      }
    });

    const currentMonthBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        createdAt: { gte: currentMonthStart },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      }
    });

    const lastMonthBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        createdAt: { 
          gte: lastMonthStart, 
          lte: lastMonthEnd 
        },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      }
    });

    // 3. Taux d'occupation (Nuits réservées / Nuits totales possibles)
    const activeResidencesCount = await this.prisma.residence.count({
      where: { ownerId, isPublished: true }
    });

    const activeVehiclesCount = await this.prisma.vehicle.count({
      where: { ownerId, isAvailable: true }
    });

    const activeProperties = activeResidencesCount + activeVehiclesCount;

    // Calculer les nuits réservées (simplifié, uniquement confirmées ou terminées)
    const totalNights = await this.prisma.reservation.aggregate({
      where: {
        residence: { ownerId },
        // Inclure uniquement les réservations confirmées ou terminées
        OR: [
          { ownerConfirmedAt: { not: null } },
          { status: 'CONFIRMED' },
          { status: 'CONFIRMEE' },
          { status: 'COMPLETED' },
          { status: 'TERMINEE' },
        ],
        // Exclure les réservations annulées et en attente
        NOT: {
          status: {
            in: ['CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING']
          }
        }
      },
      _sum: {
        // Si vous avez un champ nights, sinon calculer depuis startDate/endDate
        // nights: true
      }
    });

    // Taux d'occupation simplifié (à améliorer selon votre modèle)
    const maxNights = activeProperties * 30; // 30 jours par bien par mois
    const occupationRate = maxNights > 0 
      ? Math.round((totalNights._sum.nights || 0) / maxNights * 100)
      : 0;

    // 4. Données pour le graphique (6 derniers mois)
    const chartData = await this.getMonthlyRevenue(ownerId);

    // 5. Calculer les tendances
    const revenueTrend = this.calculateTrend(currentMonthOwnerRevenue, lastMonthOwnerRevenue);
    const bookingsTrend = this.calculateTrend(currentMonthBookings, lastMonthBookings);

    return {
      totalRevenue: totalRevenue,
      revenueTrend: revenueTrend,
      totalBookings: totalBookings,
      bookingsTrend: bookingsTrend,
      occupationRate: occupationRate,
      occupationTrend: 0, // À calculer si nécessaire
      activeProperties: activeProperties,
      propertiesTrend: 0, // À calculer si nécessaire
      chartData: chartData,
    };
  }

  private calculateTrend(current: number, previous: number): number {
    if (!previous || previous === 0) return 0;
    return Math.round(((current - previous) / previous) * 100 * 10) / 10; // 1 décimale
  }

  /**
   * Récupérer les revenus mensuels pour les 6 derniers mois
   * Utilise une requête SQL brute pour le GROUP BY sur les dates
   */
  private async getMonthlyRevenue(ownerId: string) {
    // IMPORTANT: Utiliser Prisma.$queryRaw avec des templates pour éviter l'injection SQL
    const sixMonthsAgo = subMonths(new Date(), 6);
    
    // Requête SQL sécurisée avec Prisma
    const results = await this.prisma.$queryRaw<Array<{
      month: string;
      total: bigint;
    }>>`
      SELECT 
        DATE_FORMAT(Reservation.createdAt, '%b') as month,
        SUM(Reservation.totalPrice) as total
      FROM Reservation
      JOIN Residence ON Reservation.residenceId = Residence.id
      WHERE Residence.ownerId = ${ownerId}
        AND (
          Reservation.ownerConfirmedAt IS NOT NULL
          OR Reservation.status IN ('CONFIRMED', 'CONFIRMEE', 'COMPLETED', 'TERMINEE')
        )
        AND Reservation.status NOT IN ('CANCELLED', 'CANCELED', 'ANNULEE', 'ANNULE', 'PENDING')
        AND Reservation.createdAt >= ${sixMonthsAgo}
      GROUP BY DATE_FORMAT(Reservation.createdAt, '%Y-%m'), DATE_FORMAT(Reservation.createdAt, '%b')
      ORDER BY Reservation.createdAt ASC
    `;

    // Normaliser les résultats et calculer 90% pour le propriétaire
    const normalized = results.map(result => ({
      month: result.month,
      total: Math.round(Number(result.total) * 0.9), // 90% pour le propriétaire
    }));

    // S'assurer que tous les mois ont une valeur (même 0)
    const now = new Date();
    const months = [];
    for (let i = 5; i >= 0; i--) {
      const monthDate = subMonths(now, i);
      const monthKey = format(monthDate, 'MMM');
      
      const found = normalized.find(r => r.month === monthKey);
      months.push({
        month: monthKey,
        total: found ? found.total : 0,
      });
    }

    return months;
  }
}
```

### 2. Contrôleur Sécurisé

`src/stats/stats.controller.ts`

```typescript
import { Controller, Get, UseGuards, Request } from '@nestjs/common';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { StatsService } from './stats.service';

@Controller('api/owner/stats')
@UseGuards(JwtAuthGuard)
export class StatsController {
  constructor(private statsService: StatsService) {}

  @Get()
  async getMyStats(@Request() req) {
    // SÉCURITÉ CRITIQUE : Utiliser l'ID du token JWT, pas un paramètre d'URL
    // Ignorer complètement tout ownerId passé en paramètre
    const ownerId = req.user.id; // Depuis le payload JWT
    
    // Vérifier que l'utilisateur est bien un propriétaire
    if (req.user.role !== 'owner' && req.user.role !== 'proprietaire') {
      throw new ForbiddenException('Accès réservé aux propriétaires');
    }
    
    return this.statsService.getOwnerStats(ownerId);
  }
}
```

### 3. Module Stats

`src/stats/stats.module.ts`

```typescript
import { Module } from '@nestjs/common';
import { StatsController } from './stats.controller';
import { StatsService } from './stats.service';
import { PrismaModule } from '../prisma/prisma.module';

@Module({
  imports: [PrismaModule],
  controllers: [StatsController],
  providers: [StatsService],
  exports: [StatsService],
})
export class StatsModule {}
```

## ⚠️ Points d'attention

### 1. Sécurité
- ✅ **JAMAIS** accepter `ownerId` en paramètre
- ✅ Utiliser **uniquement** `req.user.id` depuis le token JWT
- ✅ Vérifier le rôle de l'utilisateur

### 2. Performance
- ✅ Utiliser `Prisma.aggregate()` pour les sommes
- ✅ Utiliser `Prisma.$queryRaw` avec templates pour le GROUP BY
- ✅ Ne jamais charger toutes les réservations en mémoire

### 3. Devises
- ✅ Stocker `totalPrice` en **INT** (centimes) ou **Decimal(15,2)** en DB
- ✅ Calculer 90% pour le propriétaire (10% commission)
- ✅ Formater en FCFA côté frontend

### 4. Réservations annulées et en attente
- ✅ **EXCLURE** les réservations annulées du calcul des revenus
- ✅ **EXCLURE** les réservations en attente (pending) du calcul des revenus
- ✅ **INCLURE** uniquement les réservations confirmées par le propriétaire (`ownerConfirmedAt` existe)
- ✅ **INCLURE** les réservations avec statut `CONFIRMED`, `CONFIRMEE`, `COMPLETED`, `TERMINEE`
- ✅ Ne pas compter les réservations avec statut `CANCELLED`, `CANCELED`, `ANNULEE`, `ANNULE`, `PENDING`
- ✅ Les réservations annulées et en attente ne doivent pas apparaître dans les revenus totaux ni dans le graphique
- ⚠️ **IMPORTANT** : Quand un propriétaire approuve une réservation, `ownerConfirmedAt` est défini et la réservation doit apparaître dans les revenus

### 4. Données vides
- ✅ Retourner un tableau avec `total: 0` pour les mois sans données
- ✅ Gérer les cas où `_sum.totalPrice` est `null`

## 📊 Format de réponse attendu

```json
{
  "totalRevenue": 2450000,
  "revenueTrend": 12.5,
  "totalBookings": 48,
  "bookingsTrend": 8.2,
  "occupationRate": 78,
  "occupationTrend": 0,
  "activeProperties": 12,
  "propertiesTrend": 0,
  "chartData": [
    { "month": "Oct", "total": 450000 },
    { "month": "Nov", "total": 520000 },
    { "month": "Dec", "total": 890000 },
    { "month": "Jan", "total": 710000 },
    { "month": "Feb", "total": 0 },
    { "month": "Mar", "total": 0 }
  ]
}
```

## 🔄 Intégration avec Laravel

Le service Laravel `StatsService` appelle automatiquement cet endpoint. Si l'endpoint n'existe pas encore, le contrôleur Laravel fait un fallback sur un calcul local (moins performant).

Une fois l'endpoint NestJS implémenté, les performances seront considérablement améliorées grâce aux agrégations SQL.

