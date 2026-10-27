import { FabricObject } from "fabric";

/*
 * fabric 7 cambió el punto de anclaje por defecto a center/center. Todo el editor
 * (plantillas, diseños guardados y objetos nuevos) se armó con left/top, el valor
 * de fabric 6: se fija acá una sola vez, antes de crear cualquier objeto.
 */
FabricObject.ownDefaults.originX = "left";
FabricObject.ownDefaults.originY = "top";
