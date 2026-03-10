import AppLogoIcon from '@/components/app/app-logo-icon';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface AuthLayoutProps {
    children: ReactNode;
    title: string;
    description: string;
}

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10" {...props}>
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <Link
                        href={home()}
                        className="inline-flex w-fit items-center text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {'< Back to home'}
                    </Link>
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex items-center justify-center">
                                <AppLogoIcon className="h-16 w-auto object-contain" alt="SIKEDUL" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
