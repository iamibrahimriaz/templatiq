import { useState, useEffect, useRef } from '@wordpress/element';
import ReactSVG from 'react-inlinesvg';
import { Link } from 'react-router-dom';
import { SingleTemplateStyle } from './style';

import crownIcon from "@icon/crown.svg";
import cartIcon from "@icon/cart.svg";
import heartIcon from "@icon/heart.svg";
import heartSolidIcon from "@icon/heart-solid.svg";
import downloadIcon from "@icon/download-alt.svg";
import templateImg1 from "@images/template/1.svg";
import Popup from '@components/Popup';

import { select, useSelect, withSelect, resolveSelect, dispatch } from '@wordpress/data';

import store from '../../store';
console.log('Store: ', store)

const SingleTemplate = (item) => {
    let { slug, preview_link, purchase_url, thumbnail, title, price, number_of_downloads, number_of_bookmarks, categories, required_plugins } = item;

    const [isModalOpen, setModalOpen] = useState(false);
	const [addedToFavorite, addFavorite] = useState(false);
    const [currentFavoriteCount, setCurrentFavoriteCount] = useState(number_of_bookmarks);
    const [installablePlugins, setInstallablePlugins] = useState([]);

    const templateRef = useRef(null);

    // dispatch( store ).setFav( slug, '10');
    // const favCountList = resolveSelect( store ).getFav( slug).then( console.log('getFav Resolved') );

    const favCountList = select( store ).getFav( slug);
    console.log('favCountList: ', favCountList)

    let addModal = async (e) => {
        e.preventDefault();
        document.querySelector(".templatiq").classList.add("templatiq-overlay-enable");
    
        // Add the class to the root div using templateRef
        if (templateRef.current) {
            templateRef.current.classList.add('modal-open');
        }
    
        try {
            await handlePlugins(required_plugins);
            setModalOpen(true);
        } catch (error) {
            // Handle error if needed
            console.error('Error fetching installable plugins:', error);
        }
    }

    let handleFavorite = ( e ) => {
		e.preventDefault();
		addFavorite( ! addedToFavorite );
	}

    useEffect(() => {
        setModalOpen(false);

        // This will be triggered whenever addedToFavorite changes
        setCurrentFavoriteCount(addedToFavorite ? Number(currentFavoriteCount) + 1 : number_of_bookmarks);
        dispatch( store ).setFav( slug, addedToFavorite ? Number(currentFavoriteCount) + 1 : number_of_bookmarks);
    }, [addedToFavorite, setModalOpen]);

	const handlePlugins = async (plugins) => {
        const response = await fetch(`${template_market_obj.rest_args.endpoint}/dependency/check`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': template_market_obj.rest_args.nonce,
            },
            body: JSON.stringify({
                plugins: plugins
            }),
        });
    
        if (!response.ok) {
            throw new Error('Error Occurred');
        }
    
        const data = await response.json();
        setInstallablePlugins(data);
    }; 


    useEffect(() => {
        handlePlugins(required_plugins);
    }, [required_plugins]);


    return (
        <SingleTemplateStyle className="templatiq__template__single" ref={templateRef}>

            {isModalOpen && installablePlugins && (
                <Popup item={item} installable_plugins={installablePlugins} onClose={() => setModalOpen(false)} />
            )}

            <div className="templatiq__template__single__img">
                <img src={thumbnail ? thumbnail : templateImg1} alt={title} />
                <div className="templatiq__template__single__overlay"></div>
                <div className="templatiq__template__single__info">
                    <div className="templatiq__template__single__info__meta">
                        {
                            price > 0 ?
                            <span className="templatiq__template__single__info__meta__item pro-item">
                                <ReactSVG src={ crownIcon } width={16} height={16} />
                                Pro
                            </span> : ''
                        }
                        
                    </div>
                    <div className="templatiq__template__single__info__action">
                        <a href={preview_link} className="templatiq__template__single__info__action__link">
                            Live Demo
                        </a>
                        {
                            price > 0 ? 
                            <a href={purchase_url} className="templatiq__template__single__info__action__link purchase-btn">
                                <ReactSVG src={ cartIcon } width={14} height={14} />
                                Purchase
                            </a> :
                            <a 
                                href="#" 
                                className="templatiq__template__single__info__action__link insert-btn" 
                                onClick={(e) => addModal(e)}
                            >
                                <ReactSVG src={ downloadIcon } width={14} height={14} />
                                Insert
                            </a>

                        }
                    </div>
                    <div className="templatiq__template__single__info__required">
                        {required_plugins && required_plugins.map((plugin, index) => (
                            <a key={index} href="#" className="templatiq__template__single__info__required__item templatiq-tooltip" data-info={plugin.name}>
                                <img src={`${template_market_obj.assets_url}/svg/icon/${plugin.slug}.svg`} width={28} height={28} />
                            </a>
                        ))}
                    </div>
                </div> 
            </div>
            <div className="templatiq__template__single__content">
                <h3 className="templatiq__template__single__title">
                    <Link to={`/template/${slug}`}>{title ? title : 'DDoctors'}</Link>
                </h3>
                <div className="templatiq__template__single__cat">
                    {categories && categories.map((category, index) => (
                        <a key={index} href="#" className="templatiq__template__single__cat__link">{category}</a>
                    ))}
                </div>
                <div className="templatiq__template__single__quickmeta">
                    {
                        price > 0 ?
                        <span className="templatiq__template__single__quickmeta__item pro-item">{`${price ? '$' + price : ''}`}
                        </span> :
                        <span className="templatiq__template__single__quickmeta__item free-item">Free</span>
                    }
                    
                    <span className="templatiq__template__single__quickmeta__item templatiq-tooltip" data-info="Total Downloads">
                        <ReactSVG src={ downloadIcon } width={14} height={14} />
                        {number_of_downloads ? number_of_downloads : ''}
                    </span>
                    <a href="#" className={`templatiq__template__single__quickmeta__item favorite-btn templatiq-tooltip ${addedToFavorite ? 'active' : ''}`} data-info={addedToFavorite ? 'Added to Favourite' : 'Add to Favourite'} onClick={handleFavorite}>
                        <ReactSVG src={ addedToFavorite ? heartSolidIcon : heartIcon } width={14} height={14} />
                        {currentFavoriteCount ? currentFavoriteCount : ''}
                    </a>
                </div>
            </div>
        </SingleTemplateStyle>
    )
}

export default SingleTemplate;