const initialListings = [
  {name:"仕出し・弁当・宴会 大見屋",category:"飲食・仕出し",keywords:"弁当 和食 宴会",address:"安城市城南町1丁目16-3",phone:"0566-76-5005",status:"確認済み",statusClass:"",note:"旧版の住所・電話と、安城商工会議所の近年資料への掲載を照合。",source:"https://anjo-cci.or.jp/imgdb/kaihou/59.pdf"},
  {name:"株式会社デッサン",former:"旧掲載名：有限会社早川工業",category:"建設・工業",address:"安城市東別所町屋敷71",phone:"電話番号は要確認",status:"名称変更",statusClass:"changed",note:"旧掲載事業者は2017年に商号変更。所在地も法人情報と照合。",source:"https://www.houjin-bangou.nta.go.jp/"},
  {name:"ABホテル安城",category:"サービス・レジャー",keywords:"宿泊 ホテル",address:"安城市末広町8-20",phone:"0566-70-7812",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"},
  {name:"café angora",category:"飲食・仕出し",keywords:"カフェ 喫茶 コーヒー",address:"安城市花ノ木町8-6",phone:"0566-55-7878",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"},
  {name:"OHANA CURRY（オハナカレー）",category:"飲食・仕出し",address:"安城市御幸本町10-15",phone:"0566-95-7772",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"},
  {name:"TEA STAND ROB 安城店",category:"飲食・仕出し",address:"安城市御幸本町6-6 奥井ビル",phone:"0566-91-6511",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"},
  {name:"EIGHT ART HOUSE",category:"ショップ",address:"安城市末広町8-4 DENCITY 1F",phone:"0566-57-7039",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"},
  {name:"はんの川島印房",category:"ショップ",address:"安城市花ノ木町1-10",phone:"0566-76-2863",status:"確認済み",statusClass:"",note:"安城市つながる商店街の加盟店情報で確認。",source:"https://anjo-tsunagari.com/shop_list"}
];

const listings = [
  ...officialListings,
  ...initialListings.filter(item => !officialListings.some(current => current.name === item.name))
];

const grid = document.querySelector('#listing-grid');
const empty = document.querySelector('#empty-state');
const summary = document.querySelector('#result-summary');
const input = document.querySelector('#search-input');
let activeCategory = 'すべて';

function escapeHtml(value){return String(value).replace(/[&<>'"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));}
function normalizeSearch(value){
  return String(value).normalize('NFKC').toLowerCase()
    .replace(/café|cafe/g,'カフェ')
    .replace(/[\s　・･（）()\-‐‑–—]/g,'');
}
function render(){
  const term = normalizeSearch(input.value.trim());
  const filtered = listings.filter(item => {
    const categoryMatch = activeCategory === 'すべて' || item.category === activeCategory;
    const text = normalizeSearch([item.name,item.former,item.category,item.keywords,item.address,item.phone].join(' '));
    return categoryMatch && (!term || text.includes(term));
  });
  grid.innerHTML = filtered.map(item => `<article class="listing-card">
    <div class="listing-meta"><span class="listing-category">${escapeHtml(item.category)}</span><span class="badge ${item.statusClass}">${escapeHtml(item.status)}</span></div>
    <h3>${escapeHtml(item.name)}</h3>${item.former?`<p class="former-name">${escapeHtml(item.former)}</p>`:''}
    <p class="address">住所　${escapeHtml(item.address)}</p><p class="phone">電話　${escapeHtml(item.phone)}</p>
    <p class="listing-note">${escapeHtml(item.note)}</p>
    <a class="source-link" href="${item.source}" target="_blank" rel="noopener noreferrer">確認に使用した情報元 ↗</a>
  </article>`).join('');
  empty.hidden = filtered.length !== 0;
  summary.textContent = `${filtered.length}件を表示中（2026年9月12日確認）`;
}

document.querySelector('#search-form').addEventListener('submit',event=>{event.preventDefault();render();document.querySelector('#directory').scrollIntoView({behavior:'smooth'});});
document.querySelectorAll('[data-category]').forEach(button=>button.addEventListener('click',()=>{
  activeCategory=button.dataset.category;
  document.querySelectorAll('.chip').forEach(chip=>chip.classList.toggle('active',chip.dataset.category===activeCategory));
  render();document.querySelector('#directory').scrollIntoView({behavior:'smooth'});
}));
input.addEventListener('input',render);
render();
