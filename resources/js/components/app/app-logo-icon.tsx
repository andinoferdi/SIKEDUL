import { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    const { alt = 'SIKEDUL', ...rest } = props;

    return <img src="/sikedul1.png" alt={alt} {...rest} />;
}
