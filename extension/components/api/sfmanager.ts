import http from '@/api/http';
import useSWR from 'swr';

export interface GameState {
  activeSessionName: string;
  numConnectedPlayers: number;
  playerLimit: number;
  techTier: number;
  activeSchematic: string;
  gamePhase: string;
  isGameRunning: boolean;
  totalGameDuration: number;
  isGamePaused: boolean;
  averageTickRate: number;
  autoLoadSessionName: string;
}

export type Access = 'admin' | 'initial' | 'client' | 'none';

export interface Status {
  reachable: boolean;
  claimed: boolean | null;
  access: Access;
  state: GameState | null;
}

export interface Connection {
  address: string;
  port: number;
  reliable_port: number;
  reliable_port_allocated: boolean;
  checks: { game_tcp: boolean; reliable_tcp: boolean };
}

export interface ClaimData {
  server_name: string;
  admin_password: string;
  client_password?: string;
  new_game?: NewGame;
}

export const STARTING_LOCATIONS = ['Grass Fields', 'Rocky Desert', 'Northern Forest', 'Dune Desert'];

const base = (uuid: string) => `/api/client/extensions/sfmanager/servers/${uuid}`;

export const useStatus = (uuid: string) =>
  useSWR<Status>(['sfmanager:status', uuid], () => http.get(`${base(uuid)}/status`).then((r) => r.data), {
    refreshInterval: 10000,
  });

export const getConnection = (uuid: string): Promise<Connection> =>
  http.get(`${base(uuid)}/connection`).then((r) => r.data);

export interface NewGame {
  session_name: string;
  starting_location: string;
  skip_onboarding: boolean;
}

/** Resolves with error codes of follow-up steps that failed (e.g. new game). */
export const claimServer = (uuid: string, data: ClaimData): Promise<string[]> =>
  http.post(`${base(uuid)}/claim`, data).then((r) => r.data.warnings || []);

export const newGame = (uuid: string, data: NewGame) => http.post(`${base(uuid)}/new-game`, data);

export const login = (uuid: string, password: string) => http.post(`${base(uuid)}/login`, { password });

export const logout = (uuid: string) => http.delete(`${base(uuid)}/login`);

export const saveGame = (uuid: string, saveName: string) => http.post(`${base(uuid)}/save`, { name: saveName });

/** Error code from our backend (e.g. "wrong_password", "sfm_unreachable"). */
export const errorCode = (error: any): string | undefined => error?.response?.data?.errors?.[0]?.code;

// ---- Phase 2 ----------------------------------------------------------------

export interface SaveHeader {
  saveName: string;
  sessionName: string;
  saveDateTime: string;
  playDurationSeconds: number;
  buildVersion: number;
  isModdedSave: boolean;
  isEditedSave: boolean;
  isCreativeModeEnabled: boolean;
}

export interface Sessions {
  sessions: { sessionName: string; saves: SaveHeader[] }[];
  current_session: string | null;
  autoload_session: string | null;
  save_dir: string;
}

export interface Settings {
  options: Record<string, string>;
  pending_options: Record<string, string>;
  creative_mode: boolean;
  advanced: Record<string, string>;
}

export const getSessions = (uuid: string): Promise<Sessions> => http.get(`${base(uuid)}/sessions`).then((r) => r.data);
export const loadSave = (uuid: string, saveName: string, advanced: boolean) =>
  http.post(`${base(uuid)}/saves/load`, { name: saveName, advanced });
export const deleteSave = (uuid: string, saveName: string) => http.post(`${base(uuid)}/saves/delete`, { name: saveName });
export const deleteSession = (uuid: string, session: string) => http.post(`${base(uuid)}/sessions/delete`, { session });
export const setAutoload = (uuid: string, session: string) => http.post(`${base(uuid)}/sessions/autoload`, { session });

export const getSettings = (uuid: string): Promise<Settings> => http.get(`${base(uuid)}/settings`).then((r) => r.data);
export const updateOptions = (uuid: string, settings: Record<string, string>) =>
  http.put(`${base(uuid)}/settings/options`, { settings });
export const updateAdvanced = (uuid: string, settings: Record<string, string>) =>
  http.put(`${base(uuid)}/settings/advanced`, { settings });
export const renameServer = (uuid: string, serverName: string) =>
  http.post(`${base(uuid)}/settings/rename`, { server_name: serverName });
export const setClientPassword = (uuid: string, password: string) =>
  http.post(`${base(uuid)}/settings/client-password`, { password });
export const setAdminPassword = (uuid: string, password: string, confirmation: string) =>
  http.post(`${base(uuid)}/settings/admin-password`, { password, password_confirmation: confirmation });

export const runCommand = (uuid: string, command: string): Promise<{ result: string; success: boolean }> =>
  http.post(`${base(uuid)}/command`, { command }).then((r) => r.data);
export const power = (uuid: string, action: 'restart' | 'stop', save: boolean): Promise<{ save: string | null }> =>
  http.post(`${base(uuid)}/power/${action}`, { save }).then((r) => r.data);
