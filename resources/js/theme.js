import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

const slateSurface = {
    0: '#ffffff', 50: '{slate.50}', 100: '{slate.100}', 200: '{slate.200}', 300: '{slate.300}',
    400: '{slate.400}', 500: '{slate.500}', 600: '{slate.600}', 700: '{slate.700}',
    800: '{slate.800}', 900: '{slate.900}', 950: '{slate.950}',
};

/**
 * Aura с палитрой проекта: нейтральные цвета slate и один кобальтовый акцент,
 * скругления 4px у элементов управления и 8px у окон.
 */
export const BookingPreset = definePreset(Aura, {
    primitive: {
        borderRadius: { none: '0', xs: '2px', sm: '3px', md: '4px', lg: '4px', xl: '8px' },
    },
    semantic: {
        primary: {
            50: '#eef0fe',
            100: '#dde2fd',
            200: '#bcc5fb',
            300: '#8f9cf7',
            400: '#6175f1',
            500: '#2d43e8',
            600: '#2333c4',
            700: '#1d299d',
            800: '#1a237c',
            900: '#181f63',
            950: '#0f133c',
        },
        colorScheme: {
            light: { surface: slateSurface },
            dark: { surface: slateSurface },
        },
    },
});
