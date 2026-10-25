import React, { useEffect, useRef, useState } from 'react';
import classNames from 'classnames';
import { faTerminal } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import { runCommand } from '../api/sfmanager';
import { errorText, t } from '../lang';

interface Entry {
  command: string;
  output: string;
  ok: boolean;
}

export default ({ uuid }: { uuid: string }) => {
  const [command, setCommand] = useState('');
  const [entries, setEntries] = useState<Entry[]>([]);
  const [history, setHistory] = useState<string[]>([]);
  const [historyIndex, setHistoryIndex] = useState(-1);
  const [busy, setBusy] = useState(false);
  const bottom = useRef<HTMLDivElement>(null);

  useEffect(() => bottom.current?.scrollIntoView({ block: 'nearest' }), [entries]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const cmd = command.trim();
    if (!cmd) return;
    setBusy(true);
    setHistory([cmd, ...history.filter((h) => h !== cmd)].slice(0, 50));
    setHistoryIndex(-1);
    setCommand('');
    runCommand(uuid, cmd)
      .then((r) => setEntries((list) => [...list, { command: cmd, output: r.result, ok: r.success }]))
      .catch((err) => setEntries((list) => [...list, { command: cmd, output: errorText(err), ok: false }]))
      .then(() => setBusy(false));
  };

  // Arrow keys walk through previous commands, like a real terminal.
  const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    e.preventDefault();
    const next = e.key === 'ArrowUp' ? Math.min(historyIndex + 1, history.length - 1) : Math.max(historyIndex - 1, -1);
    setHistoryIndex(next);
    setCommand(next === -1 ? '' : history[next]);
  };

  return (
    <TitledGreyBox title={t('tab.console')} icon={faTerminal}>
      <p className={'text-sm text-neutral-300 mb-3'}>{t('console.intro')}</p>
      <div className={'bg-neutral-900 rounded p-3 font-mono text-sm h-80 overflow-y-auto mb-3'}>
        {entries.map((entry, i) => (
          <div key={i} className={'mb-2'}>
            <p className={'text-neutral-400'}>&gt; {entry.command}</p>
            <pre className={classNames('whitespace-pre-wrap break-words', !entry.ok && 'text-red-400')}>
              {entry.output || '–'}
            </pre>
          </div>
        ))}
        <div ref={bottom} />
      </div>
      <form onSubmit={submit} className={'flex gap-2'}>
        <Input
          value={command}
          maxLength={512}
          placeholder={t('console.placeholder')}
          className={'font-mono'}
          onKeyDown={onKeyDown}
          onChange={(e) => setCommand(e.currentTarget.value)}
        />
        <Button type={'submit'} disabled={busy || !command.trim()}>
          {t('console.run')}
        </Button>
      </form>
    </TitledGreyBox>
  );
};
