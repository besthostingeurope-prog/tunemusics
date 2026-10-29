import{I as n,J as r,M as e,P as o,Q as u,b as a}from"./main-DrSbOir7.js";function p(){const{trans:t}=n();return r({mutationFn:s=>i(s),onSuccess:()=>{o(t(u("Subscription renewed.")))},onError:s=>e(s)})}function i({subscriptionId:t}){return a.post(`billing/subscriptions/${t}/resume`).then(s=>s.data)}export{p as u};
//# sourceMappingURL=use-resume-subscription-DUrhwb7_.js.map
