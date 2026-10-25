import React, { useEffect, useRef, useState } from 'react';
import { useBadges } from '../api/sfmanager';
import { t } from '../lang';

// Blueprint renders this inside every server row without props, so the row's
// server id is read from the surrounding link ("/server/<id>").
export default () => {
  const ref = useRef<HTMLSpanElement>(null);
  const [serverId, setServerId] = useState<string | null>(null);
  const { data } = useBadges();

  useEffect(() => {
    const href = ref.current?.closest('a')?.getAttribute('href') || '';
    const match = /\/server\/([a-z0-9-]+)/i.exec(href);
    setServerId(match ? match[1] : null);
  }, []);

  const badge = serverId && data ? data[serverId] : undefined;

  return (
    <span ref={ref}>
      {badge && (
        <span className={'inline-flex items-center text-xs text-neutral-300 mt-1'}>
          <span
            className={'inline-block w-2 h-2 rounded-full mr-1.5 ' + (badge.online ? 'bg-green-500' : 'bg-red-500')}
          />
          {badge.online ? (
            <>
              {badge.players ?? 0}/{badge.player_limit ?? '?'}
              {badge.tech_tier !== null && <> · Tier {badge.tech_tier}</>}
            </>
          ) : (
            t('badge.offline')
          )}
        </span>
      )}
    </span>
  );
};
