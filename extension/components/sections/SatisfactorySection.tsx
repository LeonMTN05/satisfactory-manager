import React, { useEffect, useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { ServerContext } from '@/state/server';
import http from '@/api/http';

// Platzhalter: prüft nur, ob das Backend der Erweiterung erreichbar ist.
const SatisfactorySection = () => {
  const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
  const [backend, setBackend] = useState<string>('…');

  useEffect(() => {
    http
      .get(`/api/client/extensions/sfmanager/ping`)
      .then(({ data }) => setBackend(`OK (v${data.version})`))
      .catch(() => setBackend('nicht erreichbar'));
  }, [uuid]);

  return (
    <PageContentBlock title={'Satisfactory'}>
      <p>Satisfactory Manager – Grundgerüst.</p>
      <p>Backend: {backend}</p>
    </PageContentBlock>
  );
};

export default SatisfactorySection;
