// Créez une classe SchedulerManager.java

package service;

import java.util.Timer;
import java.util.TimerTask;
import java.util.Calendar;

public class SchedulerManager {

    private static Timer timer;

    /**
     * Démarre la vérification automatique quotidienne à 9h
     */
    public static void startDailyCheck() {
        // 1. Lancer une vérification IMMÉDIATE au démarrage de l'appli
        // On utilise un thread pour ne pas ralentir l'ouverture de la fenêtre
        new Thread(() -> {
            System.out.println("⏳ Vérification initiale des alertes au démarrage...");
            TaskAlertScheduler scheduler = new TaskAlertScheduler();
            scheduler.checkAndNotifyAll();
        }).start();

        // 2. Programmer la vérification récurrente pour les jours suivants
        timer = new Timer(true);

        // Calculer le délai jusqu'à 9h demain
        Calendar tomorrow9AM = Calendar.getInstance();
        tomorrow9AM.add(Calendar.DAY_OF_MONTH, 1);
        tomorrow9AM.set(Calendar.HOUR_OF_DAY, 9);
        tomorrow9AM.set(Calendar.MINUTE, 0);
        tomorrow9AM.set(Calendar.SECOND, 0);
        // Si 9h est déjà passé aujourd'hui, on passe à demain
        if (Calendar.getInstance().after(tomorrow9AM)) {
            tomorrow9AM.add(Calendar.DAY_OF_MONTH, 1);
        }

        long delay = tomorrow9AM.getTimeInMillis() - System.currentTimeMillis();
        long period = 24 * 60 * 60 * 1000; // 24 heures

        timer.scheduleAtFixedRate(new TimerTask() {
            @Override
            public void run() {
                try {
                System.out.println("🔔 Exécution automatique des alertes...");
                TaskAlertScheduler scheduler = new TaskAlertScheduler();
                scheduler.checkAndNotifyAll();
                } catch (Exception e) {
                    System.err.println("❌ Erreur lors du scheduler : " + e.getMessage());
                }
            }
        }, delay, period);

        System.out.println("✅ Vérification automatique activée (tous les jours à 9h)");
    }

    public static void stop() {
        if (timer != null) {
            timer.cancel();
            timer.purge(); // Nettoie la mémoire
            System.out.println("❌ Vérification automatique désactivée");
        }
    }
}