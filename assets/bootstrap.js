import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartController from './controllers/chart_controller.js';
import EmployeeAutocompleteController from './controllers/employee_autocomplete_controller.js';

const app = startStimulusApp();
app.register('chart', ChartController);
app.register('employee-autocomplete', EmployeeAutocompleteController);
