import Dexie from 'dexie'

const db = new Dexie('PalmCoreDB')

db.version(1).stores({
    cachedWorkOrders: '&id, status, cachedAt',
    cachedEquipment: '&id, cachedAt',
    pendingActions: '++id, action_type, work_order_id, status, created_at',
})

// v2: adds alert_id index to pendingActions for alert action deduplication
db.version(2).stores({
    cachedWorkOrders: '&id, status, cachedAt',
    cachedEquipment: '&id, cachedAt',
    // ++id = auto-increment PK, ensures insertion order for sequential sync
    pendingActions: '++id, action_type, work_order_id, alert_id, status, created_at',
})

export default db
