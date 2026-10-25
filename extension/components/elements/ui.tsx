import React from 'react';
import classNames from 'classnames';
import useFlash from '@/plugins/useFlash';
import { errorText } from '../lang';

export const FLASH_KEY = 'sfmanager';

/** Flash helpers bound to this extension's flash area. */
export const useNotify = () => {
  const { addFlash, clearFlashes } = useFlash();
  return {
    clear: () => clearFlashes(FLASH_KEY),
    success: (message: string) => addFlash({ key: FLASH_KEY, type: 'success', message }),
    error: (error: any) => addFlash({ key: FLASH_KEY, type: 'error', message: errorText(error) }),
    errorText: (message: string) => addFlash({ key: FLASH_KEY, type: 'error', message }),
    warning: (message: string) => addFlash({ key: FLASH_KEY, type: 'warning', message }),
  };
};

export const Dot = ({ ok }: { ok: boolean }) => (
  <span className={classNames('inline-block w-2.5 h-2.5 rounded-full mr-2', ok ? 'bg-green-500' : 'bg-red-500')} />
);

export const Stat = ({ label, value, className }: { label: string; value: React.ReactNode; className?: string }) => (
  <div className={'bg-neutral-800 rounded p-3'}>
    <p className={'text-xs uppercase text-neutral-400'}>{label}</p>
    <p className={classNames('text-lg font-semibold mt-1 truncate', className)}>{value}</p>
  </div>
);

export const Field = ({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) => (
  <div className={'mb-4'}>
    <label className={'block text-xs uppercase text-neutral-200 mb-1 sm:mb-2'}>{label}</label>
    {children}
    {hint && <p className={'text-xs text-neutral-400 mt-1'}>{hint}</p>}
  </div>
);

/** Seconds → "12 h 34 min" */
export const formatDuration = (seconds: number): string => {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return h > 0 ? `${h} h ${m} min` : `${m} min`;
};

/** "/Game/.../GP_Project_Assembly_Phase_2.GP_..." → "2" */
export const formatPhase = (phase: string): string => {
  const match = /Phase_(\d+)/.exec(phase || '');
  return match ? match[1] : '–';
};

/** "Session_manual_20261025-1430" – only characters Satisfactory accepts in save names. */
export const defaultSaveName = (session: string, suffix = 'manual') => {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  const stamp = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}`;
  return `${(session || 'save').replace(/[^\w -]/g, '').slice(0, 40)}_${suffix}_${stamp}`;
};

/** "2026.10.25-12.34.45" → local date/time string */
export const formatSaveDate = (value: string): string => {
  const m = /^(\d{4})\.(\d{2})\.(\d{2})-(\d{2})\.(\d{2})\.(\d{2})$/.exec(value || '');
  if (!m) return value;
  // The server reports UTC.
  const date = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]));
  return date.toLocaleString();
};

export const Badge = ({ children, className }: { children: React.ReactNode; className?: string }) => (
  <span className={classNames('text-xs px-2 py-0.5 rounded bg-neutral-600 text-neutral-100 ml-2', className)}>
    {children}
  </span>
);
