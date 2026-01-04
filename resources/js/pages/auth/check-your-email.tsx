import { login } from '@/routes';
import { Head, Link } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import AuthLayout from './layout';

export default function CheckYourEmail() {
    return (
        <>
            <Head title="Cek Email Anda" />

            <AuthLayout
                title="Cek Email Anda"
                description="Kami telah mengirimkan link verifikasi ke email Anda"
            >
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-4 rounded-lg border bg-card p-6 text-card-foreground shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <MailCheck className="h-6 w-6 text-primary" />
                        </div>
                        <div className="space-y-2 text-center">
                            <p className="text-sm text-muted-foreground">
                                Silakan cek inbox email Anda dan klik link verifikasi yang telah kami kirimkan.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Setelah email terverifikasi, Anda dapat login ke akun Anda.
                            </p>
                        </div>
                    </div>

                    <div className="text-center text-sm">
                        Sudah verifikasi?{' '}
                        <Link
                            href={login()}
                            className="underline underline-offset-4 hover:text-primary"
                        >
                            Login sekarang
                        </Link>
                    </div>
                </div>
            </AuthLayout>
        </>
    );
}
