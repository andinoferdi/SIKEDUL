import AppLogoIcon from '@/components/app/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center gap-2">
            <AppLogoIcon className="h-12 w-12 object-contain" alt="SIKEDUL" />
            <span className="text-lg font-bold tracking-tight">SIKEDUL</span>
        </div>
    );
}
