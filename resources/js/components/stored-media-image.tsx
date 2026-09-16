import { useEffect, useState } from 'react';

interface StoredMediaImageProps {
   src?: string | null;
   alt: string;
   className?: string;
}

const StoredMediaImage = ({ src, alt, className }: StoredMediaImageProps) => {
   const [failed, setFailed] = useState(false);

   useEffect(() => {
      setFailed(false);
   }, [src]);

   if (!src || failed) {
      return null;
   }

   return <img src={src} alt={alt} className={className} onError={() => setFailed(true)} />;
};

export default StoredMediaImage;
