# Store sessions in Redis

## Context

Incident INC-2291: every deploy logged all customers out, because sessions lived on the local disk of
each web node and a deploy replaces the nodes. Ticket OPS-412 asked for a fix before the spring sale.

## Options

1. Sticky sessions on the load balancer, sessions kept on disk.
2. Sessions in PostgreSQL through `PdoSessionHandler`.
3. Sessions in Redis through `RedisSessionHandler`.

## Decision

Option 3. Option 1 still loses every session of a node that dies, and a deploy still logs everyone
out. Option 2 adds a write to the primary database on every request, which section 2 of ADR-0004
already identified as our bottleneck during sales.

## Consequences

- Redis becomes a hard dependency of login: when it is down, nobody can sign in, but the catalog stays
  browsable because anonymous pages do not start a session.
- The Redis instance runs with `maxmemory-policy noeviction`, so a full Redis refuses new sessions
  instead of silently logging people out.
- Sessions expire after two hours of inactivity, matching `gc_maxlifetime`.
