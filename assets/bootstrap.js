import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartjsController from '@symfony/ux-chartjs/controller';

import AuthShellController from './controllers/auth_shell_controller.js';
import ClockPillController from './controllers/clock_pill_controller.js';
import PasswordVisibilityController from './controllers/password_visibility_controller.js';
import LoginFormController from './controllers/login_form_controller.js';
import RegistrationMeterController from './controllers/registration_meter_controller.js';
import RegistrationFormController from './controllers/registration_form_controller.js';
import OrbitCardController from './controllers/orbit_card_controller.js';
import StreakMeterController from './controllers/streak_meter_controller.js';
import PulseCardController from './controllers/pulse_card_controller.js';
import NotificationHeatmapController from './controllers/notification_heatmap_controller.js';
import FocusQueueController from './controllers/focus_queue_controller.js';
import TimelinePeekController from './controllers/timeline_peek_controller.js';

const app = startStimulusApp();
app.register('symfony--ux-chartjs--chart', ChartjsController);

app.register('auth-shell', AuthShellController);
app.register('clock-pill', ClockPillController);
app.register('password-visibility', PasswordVisibilityController);
app.register('login-form', LoginFormController);
app.register('registration-meter', RegistrationMeterController);
app.register('registration-form', RegistrationFormController);
app.register('orbit-card', OrbitCardController);
app.register('streak-meter', StreakMeterController);
app.register('pulse-card', PulseCardController);
app.register('notification-heatmap', NotificationHeatmapController);
app.register('focus-queue', FocusQueueController);
app.register('timeline-peek', TimelinePeekController);
