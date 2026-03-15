# 🔍 Guide de débogage - Endpoint /api/owner/stats

## ❌ Erreur actuelle

```
404 (Cannot GET /api/owner/stats)
```

Cette erreur indique que l'endpoint n'est pas encore implémenté côté NestJS ou qu'il y a un problème de configuration de route.

## ✅ Solution : Implémentation côté NestJS

### 1. Créer le contrôleur

`src/stats/owner-stats.controller.ts`

```typescript
import { Controller, Get, UseGuards, Req, ForbiddenException } from '@nestjs/common';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { StatsService } from './stats.service';

@Controller('owner/stats') // NestJS préfixera avec /api si configuré globalement
@UseGuards(JwtAuthGuard)
export class OwnerStatsController {
  constructor(private readonly statsService: StatsService) {}

  @Get()
  async getStats(@Req() req) {
    // SÉCURITÉ CRITIQUE : Utiliser l'ID du token JWT, pas un paramètre
    // Vérifier la structure de votre JWT (peut être req.user.sub ou req.user.id)
    const ownerId = req.user.sub || req.user.id || req.user.userId;
    
    if (!ownerId) {
      throw new ForbiddenException('Impossible de déterminer l\'identité du propriétaire');
    }
    
    // Vérifier que l'utilisateur est bien un propriétaire
    if (req.user.role !== 'owner' && req.user.role !== 'proprietaire') {
      throw new ForbiddenException('Accès réservé aux propriétaires');
    }
    
    return this.statsService.getOwnerStats(ownerId);
  }
}
```

### 2. Créer le service

`src/stats/stats.service.ts`

```typescript
import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { startOfMonth, endOfMonth, subMonths, format } from 'date-fns';

@Injectable()
export class StatsService {
  constructor(private prisma: PrismaService) {}

  async getOwnerStats(ownerId: string) {
    const now = new Date();
    const currentMonthStart = startOfMonth(now);
    const lastMonthStart = startOfMonth(subMonths(now, 1));
    const lastMonthEnd = endOfMonth(subMonths(now, 1));

    // 1. Revenu Total & Comparaison mensuelle
    // IMPORTANT: Calculer 90% du prix total pour le propriétaire
    const revenueData = await this.prisma.reservation.aggregate({
      where: { 
        residence: { ownerId },
        status: 'PAID' 
      },
      _sum: { totalPrice: true },
    });

    const currentMonthRevenue = await this.prisma.reservation.aggregate({
      where: {
        residence: { ownerId },
        status: 'PAID',
        createdAt: { gte: currentMonthStart }
      },
      _sum: { totalPrice: true },
    });

    const lastMonthRevenue = await this.prisma.reservation.aggregate({
      where: {
        residence: { ownerId },
        status: 'PAID',
        createdAt: { 
          gte: lastMonthStart, 
          lte: lastMonthEnd 
        }
      },
      _sum: { totalPrice: true },
    });

    // Calculer 90% pour le propriétaire (10% commission plateforme)
    const totalRevenue = Math.round((revenueData._sum.totalPrice || 0) * 0.9);
    const currentMonthOwnerRevenue = Math.round((currentMonthRevenue._sum.totalPrice || 0) * 0.9);
    const lastMonthOwnerRevenue = Math.round((lastMonthRevenue._sum.totalPrice || 0) * 0.9);

    // 2. Nombre de réservations
    const totalBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        status: 'PAID'
      }
    });

    const currentMonthBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        status: 'PAID',
        createdAt: { gte: currentMonthStart }
      }
    });

    const lastMonthBookings = await this.prisma.reservation.count({
      where: {
        residence: { ownerId },
        status: 'PAID',
        createdAt: { 
          gte: lastMonthStart, 
          lte: lastMonthEnd 
        }
      }
    });

    // 3. Biens actifs
    const activeResidencesCount = await this.prisma.residence.count({
      where: { ownerId, isPublished: true }
    });

    const activeVehiclesCount = await this.prisma.vehicle.count({
      where: { ownerId, isAvailable: true }
    });

    const activeProperties = activeResidencesCount + activeVehiclesCount;

    // 4. Taux d'occupation (simplifié)
    const occupationRate = 0; // À calculer selon votre modèle de données

    // 5. Données pour le graphique (6 derniers mois)
    const chartData = await this.getMonthlyRevenue(ownerId);

    // 6. Calculer les tendances
    const revenueTrend = this.calculateTrend(currentMonthOwnerRevenue, lastMonthOwnerRevenue);
    const bookingsTrend = this.calculateTrend(currentMonthBookings, lastMonthBookings);

    return {
      totalRevenue: totalRevenue,
      revenueTrend: revenueTrend,
      totalBookings: totalBookings,
      bookingsTrend: bookingsTrend,
      occupationRate: occupationRate,
      occupationTrend: 0,
      activeProperties: activeProperties,
      propertiesTrend: 0,
      chartData: chartData,
    };
  }

  private calculateTrend(current: number, previous: number): number {
    if (!previous || previous === 0) return 0;
    return Math.round(((current - previous) / previous) * 100 * 10) / 10; // 1 décimale
  }

  private async getMonthlyRevenue(ownerId: string) {
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
        AND Reservation.status = 'PAID'
        AND Reservation.createdAt >= ${sixMonthsAgo}
      GROUP BY DATE_FORMAT(Reservation.createdAt, '%Y-%m'), DATE_FORMAT(Reservation.createdAt, '%b')
      ORDER BY Reservation.createdAt ASC
    `;

    // Normaliser les résultats et calculer 90% pour le propriétaire
    const normalized = results.map(result => ({
      month: result.month,
      total: Math.round(Number(result.total) * 0.9),
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

### 3. Créer le module

`src/stats/stats.module.ts`

```typescript
import { Module } from '@nestjs/common';
import { OwnerStatsController } from './owner-stats.controller';
import { StatsService } from './stats.service';
import { PrismaModule } from '../prisma/prisma.module';

@Module({
  imports: [PrismaModule],
  controllers: [OwnerStatsController],
  providers: [StatsService],
  exports: [StatsService],
})
export class StatsModule {}
```

### 4. Enregistrer le module dans AppModule

`src/app.module.ts`

```typescript
import { Module } from '@nestjs/common';
import { StatsModule } from './stats/stats.module';
// ... autres imports

@Module({
  imports: [
    StatsModule, // ⚠️ INDISPENSABLE - Ajouter cette ligne
    // ... autres modules
  ],
  // ...
})
export class AppModule {}
```

### 5. Vérifier le préfixe global

`src/main.ts`

```typescript
const app = await NestFactory.create(AppModule);

// Vérifier que le préfixe global est bien 'api'
app.setGlobalPrefix('api'); // Si cette ligne existe, la route est bien /api/owner/stats

// Activer CORS si le dashboard est sur un domaine différent
app.enableCors({
  origin: process.env.FRONTEND_URL || 'http://localhost:8000',
  credentials: true,
});

await app.listen(3000);
```

## 🔍 Vérifications à faire

### 1. Structure du JWT

Vérifier dans votre `JwtStrategy` comment le payload est structuré :

```typescript
// src/auth/strategies/jwt.strategy.ts
async validate(payload: any) {
  return {
    id: payload.sub, // ou payload.id
    email: payload.email,
    role: payload.role,
  };
}
```

Adapter `req.user.id` dans le contrôleur selon votre structure.

### 2. Ordre des routes

Vérifier qu'aucune route générique (ex: `/api/:id`) ne capture l'appel avant `owner/stats`.

### 3. Test de l'endpoint

Une fois implémenté, tester avec :

```bash
curl -X GET http://localhost:3000/api/owner/stats \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## ✅ Comportement actuel du dashboard Laravel

Le dashboard Laravel gère automatiquement l'erreur 404 :

1. **Si l'endpoint existe** : Utilise les stats depuis l'API NestJS (performant)
2. **Si l'endpoint n'existe pas (404)** : Fallback sur le calcul local (moins performant mais fonctionne)

Une fois l'endpoint NestJS implémenté, les performances seront automatiquement améliorées sans modification du code Laravel.

